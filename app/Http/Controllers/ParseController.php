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

        $url = $request->string('document_url')->toString();
        $summary = $this->parser->parseSingle($url);

        return redirect()
            ->route('parse.index')
            ->with('status', $this->formatSummary($summary));
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
