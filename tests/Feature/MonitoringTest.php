<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Awardee;
use App\Models\MonitoringRecord;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitoring_tab_is_visible_in_navigation_after_search(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Пошук');
        $response->assertSee('Моніторинг');
        $response->assertSee('Укази');
        $response->assertSee('Розбір');

        $content = $response->getContent();
        $this->assertIsString($content);
        $posSearch = mb_strpos($content, '>Пошук<');
        $posMonitoring = mb_strpos($content, '>Моніторинг<');
        $posOrders = mb_strpos($content, '>Укази<');

        $this->assertNotFalse($posSearch);
        $this->assertNotFalse($posMonitoring);
        $this->assertNotFalse($posOrders);
        $this->assertLessThan($posMonitoring, $posSearch);
        $this->assertLessThan($posOrders, $posMonitoring);
    }

    public function test_monitoring_results_are_paginated_across_pages(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            MonitoringRecord::query()->create(['full_name' => "Особа {$i} Унікальна"]);
        }

        // Page 1 via JSON shows the first 15 records, not the last ones.
        $response1 = $this->getJson(route('monitoring.indexJson', ['page' => 1]));
        $response1->assertOk();
        /** @var array{data: array<int, array<string, mixed>>, pagination: array{total: int, last_page: int}} $data1 */
        $data1 = $response1->json();
        $names1 = array_column($data1['data'], 'full_name');
        $this->assertContains('Особа 1 Унікальна', $names1);
        $this->assertNotContains('Особа 20 Унікальна', $names1);
        $this->assertSame(20, $data1['pagination']['total']);
        $this->assertSame(2, $data1['pagination']['last_page']);

        // Page 2 via JSON shows the last record, not the first ones.
        $response2 = $this->getJson(route('monitoring.indexJson', ['page' => 2]));
        $response2->assertOk();
        /** @var array{data: array<int, array<string, mixed>>, pagination: array{total: int, last_page: int}} $data2 */
        $data2 = $response2->json();
        $names2 = array_column($data2['data'], 'full_name');
        $this->assertContains('Особа 20 Унікальна', $names2);
        $this->assertNotContains('Особа 1 Унікальна', $names2);
    }

    public function test_score_bands_render_distinct_colors_for_scores_3_and_4(): void
    {
        // Score 4: name + rank + award + date within six months after submission.
        $orderFull = Order::query()->create([
            'number' => '555/2026',
            'document_url' => 'https://example.com/order-555',
            'published_at' => '2025-09-01', // Within six months of submission → date match
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $orderFull->id,
            'full_name' => 'Кравченко Іван Іванович',
            'rank' => 'генерал',
            'award' => 'орден «За мужність» III ступеня',
        ]);

        MonitoringRecord::query()->create([
            'sheet' => '1',
            'full_name' => 'Кравченко Іван Іванович',
            'rank' => 'Генерал', // Case-insensitive match → rank point
            'submission_date' => '2025-08-31',
            'award' => 'орден «За мужність» III ступеня',
        ]);

        // Score 3: name + rank + award, but order published outside the six-month window (no date match).
        $orderNoDate = Order::query()->create([
            'number' => '556/2026',
            'document_url' => 'https://example.com/order-556',
            'published_at' => '2026-03-01', // Outside six months of submission → no date match
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $orderNoDate->id,
            'full_name' => 'Довгань Петро Петрович',
            'rank' => 'генерал',
            'award' => 'орден «За мужність» III ступеня',
        ]);

        MonitoringRecord::query()->create([
            'sheet' => '2',
            'full_name' => 'Довгань Петро Петрович',
            'rank' => 'Генерал',
            'submission_date' => '2025-08-31',
            'award' => 'орден «За мужність» III ступеня',
        ]);

        $response = $this->getJson(route('monitoring.indexJson'));

        $response->assertOk();
        /** @var array{data: array<int, array<string, mixed>>, pagination: array{total: int, last_page: int}} $data */
        $data = $response->json();

        // Score 4 → emerald "максимум" badge title
        $score4Found = false;
        $score3Found = false;
        foreach ($data['data'] as $row) {
            if ($row['score'] === 4) {
                $score4Found = true;
            }

            if ($row['score'] === 3) {
                $score3Found = true;
            }
        }

        $this->assertTrue($score4Found, 'Expected a record with score 4');
        $this->assertTrue($score3Found, 'Expected a record with score 3');
    }

    public function test_can_view_monitoring_page_and_empty_state(): void
    {
        $response = $this->get(route('monitoring.index'));

        $response->assertOk();
        $response->assertSee('Моніторинг');
        $response->assertSee('Таблиця моніторингу');
        $response->assertSee('Аркуш');
        $response->assertSee('ПІБ');
        $response->assertSee('Дата подання');
        $response->assertSee('Нагорода');
        $response->assertSee('Бал');
        $response->assertDontSee('>ІПН<');
    }

    public function test_monitoring_loads_vite_assets_and_new_records_in_development(): void
    {
        $fullName = 'Нова Особа';
        $this->post(route('monitoring.store'), ['full_name' => $fullName])
            ->assertRedirect(route('monitoring.index'));

        $hotFilePath = public_path('hot');
        $previousHotFileContents = is_file($hotFilePath) ? (file_get_contents($hotFilePath) ?: '') : null;
        file_put_contents($hotFilePath, 'http://localhost:5173');

        try {
            $this->get(route('monitoring.index'))->assertOk()->assertSeeHtml('http://localhost:5173/@vite/client')->assertSeeHtml('http://localhost:5173/resources/js/app.js');
        } finally {
            if ($previousHotFileContents === null) {
                if (is_file($hotFilePath)) {
                    unlink($hotFilePath);
                }
            } else {
                file_put_contents($hotFilePath, $previousHotFileContents);
            }
        }

        $this->getJson(route('monitoring.indexJson'))
            ->assertOk()
            ->assertJsonFragment(['full_name' => $fullName]);
    }

    public function test_can_manually_add_monitoring_record(): void
    {
        $response = $this->post(route('monitoring.store'), [
            'sheet' => 'Арк. 1',
            'full_name' => 'Шовковий Олександр Володимирович',
            'tax_id' => '1234567890',
            'submission_date' => '2026-11-01',
            'award' => 'орден «За мужність» III ступеня',
        ]);

        $response->assertRedirect(route('monitoring.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('monitoring_records', [
            'sheet' => 'Арк. 1',
            'full_name' => 'Шовковий Олександр Володимирович',
            'tax_id' => '1234567890',
            'award' => 'орден «За мужність» III ступеня',
        ]);
    }

    public function test_monitoring_displays_calculated_score(): void
    {
        $order = Order::query()->create([
            'number' => '555/2026',
            'document_url' => 'https://example.com/order-555',
            'published_at' => '2026-11-10',
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'БАКАЛ Олександр Вікторович',
            'award' => 'орден «За мужність» III ступеня',
        ]);

        // Record with full match: name + award + date after submission => score 3
        MonitoringRecord::query()->create([
            'sheet' => '1',
            'full_name' => 'Бакал Олександр Вікторович',
            'tax_id' => '1111111111',
            'submission_date' => '2026-11-01',
            'award' => 'орден «За мужність» III ступеня',
        ]);

        // Record with no match => score 0
        MonitoringRecord::query()->create([
            'sheet' => '2',
            'full_name' => 'Петренко Петро Петрович',
            'tax_id' => '2222222222',
            'submission_date' => '2026-11-01',
            'award' => 'орден «За мужність» III ступеня',
        ]);

        $response = $this->getJson(route('monitoring.indexJson'));

        $response->assertOk();
        /** @var array{data: array<int, array<string, mixed>>, pagination: array{total: int, last_page: int}} $data */
        $data = $response->json();
        $names = array_column($data['data'], 'full_name');
        $this->assertContains('Бакал Олександр Вікторович', $names);
        $this->assertContains('Петренко Петро Петрович', $names);

        // Check that Бакал has a match with the order
        $bakalRow = current(array_filter($data['data'], fn (array $row): bool => $row['full_name'] === 'Бакал Олександр Вікторович'));
        $this->assertIsArray($bakalRow);
        $this->assertGreaterThan(0, $bakalRow['score']);
        $matchesSummary = is_string($bakalRow['matchesSummary'] ?? '') ? ($bakalRow['matchesSummary'] ?? '') : '';
        $this->assertStringContainsString('№555/2026', $matchesSummary);
    }

    public function test_can_import_csv(): void
    {
        $csvContent = <<<'CSV'
Аркуш,ПІБ,ІПН,Дата подання,Нагорода
1,Коваленко Іван Васильович,1234567890,2026-10-10,орден «За мужність» III ступеня
2,Мельник Сергій Петрович,0987654321,2026-10-12,медаль «Захиснику Вітчизни»
CSV;

        $file = UploadedFile::fake()->createWithContent('people.csv', $csvContent);

        $response = $this->post(route('monitoring.import'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('monitoring.index'));
        $this->assertDatabaseCount('monitoring_records', 2);
    }

    public function test_csv_import_parses_rank_column(): void
    {
        $csvContent = <<<'CSV'
Аркуш,ПІБ,Звання,Дата подання,Нагорода
1,Коваленко Іван Васильович,генерал,2026-10-10,орден «За мужність» III ступеня
CSV;

        $file = UploadedFile::fake()->createWithContent('people.csv', $csvContent);

        $response = $this->post(route('monitoring.import'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('monitoring.index'));
        $this->assertDatabaseHas('monitoring_records', [
            'full_name' => 'Коваленко Іван Васильович',
            'rank' => 'генерал',
        ]);
    }

    public function test_can_update_monitoring_record(): void
    {
        $record = MonitoringRecord::query()->create([
            'sheet' => '1',
            'full_name' => 'Старе Ім’я',
            'tax_id' => '123',
            'submission_date' => '2026-10-01',
            'award' => 'Стара Нагорода',
        ]);

        $response = $this->put(route('monitoring.update', $record->id), [
            'sheet' => '2',
            'full_name' => 'Нове Ім’я',
            'tax_id' => '456',
            'submission_date' => '2026-10-15',
            'award' => 'Нова Нагорода',
        ]);

        $response->assertRedirect(route('monitoring.index'));
        $this->assertDatabaseHas('monitoring_records', [
            'id' => $record->id,
            'full_name' => 'Нове Ім’я',
            'sheet' => '2',
            'tax_id' => '456',
            'award' => 'Нова Нагорода',
        ]);
    }

    public function test_can_delete_monitoring_record(): void
    {
        $record = MonitoringRecord::query()->create([
            'full_name' => 'Видалити Мене',
        ]);

        $response = $this->delete(route('monitoring.destroy', $record->id));

        $response->assertRedirect(route('monitoring.index'));
        $this->assertDatabaseMissing('monitoring_records', [
            'id' => $record->id,
        ]);
    }

    public function test_can_clear_all_monitoring_records(): void
    {
        MonitoringRecord::query()->create(['full_name' => 'Перший']);
        MonitoringRecord::query()->create(['full_name' => 'Другий']);

        $response = $this->delete(route('monitoring.clear'));

        $response->assertRedirect(route('monitoring.index'));
        $this->assertDatabaseCount('monitoring_records', 0);
    }

    public function test_can_export_csv(): void
    {
        $order = Order::query()->create([
            'number' => '123',
            'document_url' => 'https://example.com/order-123',
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'Тестова Особа',
            'award' => 'орден',
        ]);

        MonitoringRecord::query()->create([
            'sheet' => '1',
            'full_name' => 'Тестова Особа',
            'tax_id' => '1234567890',
            'submission_date' => '2026-10-01',
            'award' => 'орден',
        ]);

        $response = $this->get(route('monitoring.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringContainsString('Тестова Особа', $content);
        $this->assertStringContainsString('Аркуш', $content);
        $this->assertStringContainsString('Бал', $content);
    }

    public function test_export_uses_excel_friendly_csv_format(): void
    {
        MonitoringRecord::query()->create([
            'sheet' => 'Аркуш; 1',
            'full_name' => 'Шевченко Тарас',
            'award' => 'Нагорода "За відвагу", I ступеня',
        ]);

        $content = $this->get(route('monitoring.export'))->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $stream = fopen('php://memory', 'r+');
        $this->assertIsResource($stream);
        fwrite($stream, substr($content, 3));
        rewind($stream);

        $this->assertSame(['Аркуш', 'ПІБ', 'Звання', 'Дата подання', 'Нагорода', 'Бал', 'Знайдено в указах'], fgetcsv($stream, 0, ';', '"', ''));
        $this->assertSame(['Аркуш; 1', 'Шевченко Тарас', '', '', 'Нагорода "За відвагу", I ступеня', '0', ''], fgetcsv($stream, 0, ';', '"', ''));
        fclose($stream);
    }

    public function test_export_respects_visible_record_selection(): void
    {
        $visible = MonitoringRecord::query()->create(['full_name' => 'Visible Person']);
        MonitoringRecord::query()->create(['full_name' => 'Hidden Person']);

        $response = $this->post(route('monitoring.export'), ['record_ids' => json_encode([$visible->id])]);

        $response->assertOk();
        $this->assertStringContainsString('Visible Person', $response->streamedContent());
        $this->assertStringNotContainsString('Hidden Person', $response->streamedContent());
    }

    public function test_export_with_empty_selection_contains_no_records(): void
    {
        MonitoringRecord::query()->create(['full_name' => 'Hidden Person']);

        $response = $this->post(route('monitoring.export'), ['record_ids' => '[]']);

        $response->assertOk();
        $this->assertStringContainsString('Аркуш', $response->streamedContent());
        $this->assertStringNotContainsString('Hidden Person', $response->streamedContent());
    }

    public function test_export_rejects_invalid_record_selection(): void
    {
        $this->postJson(route('monitoring.export'), ['record_ids' => '[1, "invalid"]'])
            ->assertUnprocessable();
    }

    public function test_export_respects_search_and_links_preserve_it(): void
    {
        MonitoringRecord::query()->create(['full_name' => 'Visible Person']);
        MonitoringRecord::query()->create(['full_name' => 'Hidden Person']);

        $this->get(route('monitoring.index', ['q' => 'visible']))
            ->assertOk()
            ->assertSee(route('monitoring.export', ['q' => 'visible']));

        $response = $this->get(route('monitoring.export', ['q' => 'visible']));

        $response->assertOk();
        $this->assertStringContainsString('Visible Person', $response->streamedContent());
        $this->assertStringNotContainsString('Hidden Person', $response->streamedContent());
    }

    public function test_json_endpoint_applies_multiple_column_filters(): void
    {
        MonitoringRecord::query()->create([
            'sheet' => '1',
            'full_name' => 'Особа А',
            'award' => 'орден',
        ]);

        MonitoringRecord::query()->create([
            'sheet' => '2',
            'full_name' => 'Особа Б',
            'award' => 'медаль',
        ]);

        MonitoringRecord::query()->create([
            'sheet' => '1',
            'full_name' => 'Особа В',
            'award' => 'медаль',
        ]);

        // Single filter: sheet=1 should return 2 records.
        $response1 = $this->getJson(route('monitoring.indexJson', ['filters' => ['sheet:1']]));
        $response1->assertOk();
        $this->assertSame(2, $response1->json('pagination.total'));

        // Single filter: award=медаль should return 2 records.
        $response2 = $this->getJson(route('monitoring.indexJson', ['filters' => ['award:медаль']]));
        $response2->assertOk();
        $this->assertSame(2, $response2->json('pagination.total'));

        // Both filters: sheet=1 AND award=медаль should return 1 record (Особа В).
        $responseBoth = $this->getJson(route('monitoring.indexJson', ['filters' => ['sheet:1', 'award:медаль']]));
        $responseBoth->assertOk();
        /** @var array{data: array<int, array<string, mixed>>, pagination: array{total: int, last_page: int}} $dataBoth */
        $dataBoth = $responseBoth->json();
        $this->assertSame(1, $dataBoth['pagination']['total']);
        $this->assertSame('Особа В', $dataBoth['data'][0]['full_name']);
    }
}
