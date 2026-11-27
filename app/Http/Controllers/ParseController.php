<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ParseRequest;
use App\Models\Awardee;
use App\Models\Order;
use App\Models\ParseRun;
use App\Services\Monitoring\MonitoringEvaluationCache;
use App\Services\Parsers\AwardsParserService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class ParseController extends Controller
{
    public function __construct(private readonly AwardsParserService $parser) {}

    public function index(): View
    {
        return view('parse.index');
    }

    public function store(ParseRequest $request): RedirectResponse
    {
        $mode = $request->string('mode')->toString();

        if ($mode === 'rss') {
            $url = $request->string('rss_url')->toString();
            $maxItems = $request->integer('max_items');
            $summary = $this->parser->parseRss($url, $maxItems > 0 ? $maxItems : null);

            return redirect()
                ->route('parse.index')
                ->with('status', $this->formatSummary($summary));
        }

        if ($mode === 'file') {
            return $this->parseUploadedUrls($request);
        }

        $url = $request->string('document_url')->toString();
        $summary = $this->parser->parseSingle($url);

        return redirect()
            ->route('parse.index')
            ->with('status', $this->formatSummary($summary));
    }

    private function parseUploadedUrls(ParseRequest $request): RedirectResponse
    {
        $file = $request->file('urls_file');
        if (! $file instanceof UploadedFile) {
            return redirect()
                ->route('parse.index')
                ->withErrors(['urls_file' => 'Завантажте текстовий файл зі списком URL.']);
        }

        $urls = $this->readUploadedUrls($file);
        $validationError = $this->validateUploadedUrls($urls);

        if ($validationError !== null) {
            return redirect()
                ->route('parse.index')
                ->withErrors(['urls_file' => $validationError]);
        }

        $summary = $this->processUploadedUrls($urls);

        return redirect()
            ->route('parse.index')
            ->with('status', $this->formatSummary($summary));
    }

    /**
     * @return list<string>
     */
    private function readUploadedUrls(UploadedFile $file): array
    {
        $contents = $file->get();
        if (is_string($contents)) {
            $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        }

        $lines = is_string($contents) ? preg_split('/\R/', $contents) : false;

        return array_values(array_filter(
            array_map(trim(...), $lines ?: []),
            static fn (string $url): bool => $url !== '',
        ));
    }

    /**
     * @param  list<string>  $urls
     */
    private function validateUploadedUrls(array $urls): ?string
    {
        if ($urls === [] || count($urls) > 200) {
            return 'Файл має містити від 1 до 200 URL-адрес, по одній на рядок.';
        }

        foreach ($urls as $url) {
            if (! $this->isAllowedFileUrl($url)) {
                return 'Дозволені лише HTTPS-посилання на president.gov.ua.';
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $urls
     * @return array{mode: string, source_url: string, items_found: int, items_parsed: int, orders_created: int, awardees_created: int, orders_processed: int, awardees_processed: int, errors: list<string>}
     */
    private function processUploadedUrls(array $urls): array
    {
        $summary = [
            'mode' => 'file',
            'source_url' => 'Uploaded URL list',
            'items_found' => count($urls),
            'items_parsed' => 0,
            'orders_created' => 0,
            'awardees_created' => 0,
            'orders_processed' => 0,
            'awardees_processed' => 0,
            'errors' => [],
        ];

        foreach ($urls as $url) {
            $singleSummary = $this->parser->parseSingle($url);
            $summary['items_parsed'] += $singleSummary['items_parsed'];
            $summary['orders_created'] += $singleSummary['orders_created'];
            $summary['awardees_created'] += $singleSummary['awardees_created'];
            $summary['orders_processed'] += $singleSummary['orders_processed'];
            $summary['awardees_processed'] += $singleSummary['awardees_processed'];

            foreach ($singleSummary['errors'] as $error) {
                $summary['errors'][] = $url.': '.$error;
            }
        }

        return $summary;
    }

    private function isAllowedFileUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return false;
        }

        return strtolower($parts['scheme'] ?? '') === 'https'
            && in_array(strtolower($parts['host'] ?? ''), ['president.gov.ua', 'www.president.gov.ua'], true)
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && (! isset($parts['port']) || $parts['port'] === 443);
    }

    public function clearDatabase(MonitoringEvaluationCache $evaluationCache): RedirectResponse
    {
        DB::transaction(function (): void {
            Awardee::query()->delete();
            Order::withTrashed()->forceDelete();
            ParseRun::query()->delete();
        });
        $evaluationCache->invalidate();

        return redirect()
            ->route('parse.index')
            ->with('status', 'Базу даних очищено: видалено всі укази, нагороджених і записи розбору.');
    }

    /**
     * @param  array{mode: string, source_url: string, items_found: int, items_parsed: int, orders_created: int, awardees_created: int, orders_processed: int, awardees_processed: int, errors: list<string>}  $summary
     */
    private function formatSummary(array $summary): string
    {
        $message = sprintf(
            'Режим: %s. Знайдено: %d, розібрано: %d, указів: %d, нагороджених: %d.',
            $summary['mode'],
            $summary['items_found'],
            $summary['items_parsed'],
            $summary['orders_processed'],
            $summary['awardees_processed']
        );

        if ($summary['errors'] !== []) {
            $message .= ' Помилки: '.implode('; ', $summary['errors']);
        }

        return $message;
    }
}
