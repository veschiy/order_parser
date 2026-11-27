<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Services\Http\BrowserlikeClient;
use App\Services\Monitoring\MonitoringEvaluationCache;
use App\Services\Parsers\AwardsParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class ParseFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_parses_each_url_from_an_uploaded_text_file(): void
    {
        $html = '<html lang="uk"><head><title>Указ № 1/2025</title></head><body>Текст указу.</body></html>';
        $requestedUrls = [];
        app()->instance(AwardsParserService::class, $this->createTestParserService($html, $requestedUrls));

        $this->get(route('parse.index'))
            ->assertOk()->assertSee('Розбір з файлу')->assertSeeHtml('name="urls_file"');

        $response = $this->post(route('parse.store'), [
            'mode' => 'file',
            'urls_file' => UploadedFile::fake()->createWithContent(
                'decrees.txt',
                "\u{FEFF} https://www.president.gov.ua/documents/1 \r\n\r\nhttps://president.gov.ua/documents/2\n",
            ),
        ]);

        $response->assertRedirect(route('parse.index'));
        $response->assertSessionHas('status', 'Режим: file. Знайдено: 2, розібрано: 2, указів: 2, нагороджених: 0.');
        $this->assertSame([
            'https://www.president.gov.ua/documents/1',
            'https://president.gov.ua/documents/2',
        ], array_slice($requestedUrls, -2));
        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseCount('parse_runs', 2);
    }

    public function test_it_skips_existing_active_orders_and_reparses_soft_deleted_orders_from_an_uploaded_file(): void
    {
        $html = '<html lang="uk"><head><title>Указ № 1/2025</title></head><body>Текст указу.</body></html>';
        $requestedUrls = [];
        app()->instance(AwardsParserService::class, $this->createTestParserService($html, $requestedUrls));

        $activeUrl = 'https://president.gov.ua/documents/active';
        $deletedUrl = 'https://president.gov.ua/documents/deleted';
        Order::query()->create([
            'document_url' => $activeUrl,
            'parsed_at' => now(),
        ]);
        $deletedOrder = Order::query()->create([
            'document_url' => $deletedUrl,
            'parsed_at' => now(),
        ]);
        $deletedOrder->awardees()->create(['full_name' => 'Existing Awardee']);
        $deletedOrder->delete();

        $response = $this->post(route('parse.store'), [
            'mode' => 'file',
            'urls_file' => UploadedFile::fake()->createWithContent('decrees.txt', $activeUrl."\n".$deletedUrl),
        ]);

        $response->assertRedirect(route('parse.index'));

        $documentRequests = array_values(array_filter(
            $requestedUrls,
            static fn (string $url): bool => in_array($url, [$activeUrl, $deletedUrl], true),
        ));
        $this->assertSame([$deletedUrl], $documentRequests);
        $this->assertDatabaseHas('orders', [
            'document_url' => $activeUrl,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('orders', [
            'document_url' => $deletedUrl,
            'deleted_at' => null,
        ]);
    }

    public function test_it_accepts_a_file_containing_200_urls(): void
    {
        $html = '<html lang="uk"><head><title>Указ № 1/2025</title></head><body>Текст указу.</body></html>';
        $requestedUrls = [];
        app()->instance(AwardsParserService::class, $this->createTestParserService($html, $requestedUrls));
        $urls = array_map(
            static fn (int $index): string => 'https://president.gov.ua/documents/'.$index,
            range(1, 200),
        );

        $response = $this->post(route('parse.store'), [
            'mode' => 'file',
            'urls_file' => UploadedFile::fake()->createWithContent('decrees.txt', implode("\n", $urls)),
        ]);

        $response->assertRedirect(route('parse.index'));
        $response->assertSessionHas('status', 'Режим: file. Знайдено: 200, розібрано: 200, указів: 200, нагороджених: 0.');
        $this->assertSame($urls, array_slice($requestedUrls, -200));
        $this->assertDatabaseCount('orders', 200);
        $this->assertDatabaseCount('parse_runs', 200);
    }

    public function test_it_rejects_a_file_containing_more_than_200_urls(): void
    {
        $requestedUrls = [];
        app()->instance(AwardsParserService::class, $this->createTestParserService('', $requestedUrls));
        $urls = array_map(
            static fn (int $index): string => 'https://president.gov.ua/documents/'.$index,
            range(1, 201),
        );

        $response = $this->post(route('parse.store'), [
            'mode' => 'file',
            'urls_file' => UploadedFile::fake()->createWithContent('decrees.txt', implode("\n", $urls)),
        ]);

        $response->assertSessionHasErrors('urls_file');
        $this->assertSame([], $requestedUrls);
    }

    public function test_it_rejects_an_uploaded_file_with_no_urls(): void
    {
        $requestedUrls = [];
        app()->instance(AwardsParserService::class, $this->createTestParserService('', $requestedUrls));

        $response = $this->post(route('parse.store'), [
            'mode' => 'file',
            'urls_file' => UploadedFile::fake()->createWithContent('decrees.txt', "\n  \r\n"),
        ]);

        $response->assertSessionHasErrors('urls_file');
        $this->assertSame([], $requestedUrls);
    }

    public function test_it_rejects_an_uploaded_list_containing_a_url_outside_the_supported_site(): void
    {
        $requestedUrls = [];
        app()->instance(AwardsParserService::class, $this->createTestParserService('', $requestedUrls));

        $response = $this->post(route('parse.store'), [
            'mode' => 'file',
            'urls_file' => UploadedFile::fake()->createWithContent(
                'decrees.txt',
                "https://www.president.gov.ua/documents/1\nhttps://example.com/document",
            ),
        ]);

        $response->assertSessionHasErrors('urls_file');
        $this->assertSame([], $requestedUrls);
    }

    public function test_it_rejects_uploaded_urls_with_credentials_or_nonstandard_ports(): void
    {
        foreach ([
            'https://user:secret@president.gov.ua/documents/1',
            'https://president.gov.ua:444/documents/1',
        ] as $url) {
            $requestedUrls = [];
            app()->instance(AwardsParserService::class, $this->createTestParserService('', $requestedUrls));

            $response = $this->post(route('parse.store'), [
                'mode' => 'file',
                'urls_file' => UploadedFile::fake()->createWithContent('decrees.txt', $url),
            ]);

            $response->assertSessionHasErrors('urls_file');
            $this->assertSame([], $requestedUrls);
        }
    }

    public function test_it_rejects_uploads_that_are_not_text_files(): void
    {
        $requestedUrls = [];
        app()->instance(AwardsParserService::class, $this->createTestParserService('', $requestedUrls));

        $response = $this->post(route('parse.store'), [
            'mode' => 'file',
            'urls_file' => UploadedFile::fake()->create('decrees.pdf', 1, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors('urls_file');
        $this->assertSame([], $requestedUrls);
    }

    /** @param list<string> $requestedUrls */
    private function createTestParserService(string $html, array &$requestedUrls): AwardsParserService
    {
        $client = new BrowserlikeClient(
            sleeper: static function (int $microseconds): void {},
            minIntervalMs: 0,
            useCookies: false,
            requester: static function (string $url, array $_headers) use ($html, &$requestedUrls): array {
                $requestedUrls[] = $url;

                return [200, $html];
            },
        );

        return new AwardsParserService(app(MonitoringEvaluationCache::class), client: $client);
    }
}
