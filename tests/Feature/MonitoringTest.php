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
        $posSearch = mb_strpos($content, '>Пошук<');
        $posMonitoring = mb_strpos($content, '>Моніторинг<');
        $posOrders = mb_strpos($content, '>Укази<');

        $this->assertNotFalse($posSearch);
        $this->assertNotFalse($posMonitoring);
        $this->assertNotFalse($posOrders);
        $this->assertTrue($posSearch < $posMonitoring);
        $this->assertTrue($posMonitoring < $posOrders);
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

        $response = $this->get(route('monitoring.index'));

        $response->assertOk();
        $response->assertSee('Бакал Олександр Вікторович');
        $response->assertSee('Петренко Петро Петрович');
        $response->assertSee('№555/2026');
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
        fwrite($stream, substr($content, 3));
        rewind($stream);

        $this->assertSame(['Аркуш', 'ПІБ', 'Дата подання', 'Нагорода', 'Бал', 'Знайдено в указах'], fgetcsv($stream, 0, ';', '"', ''));
        $this->assertSame(['Аркуш; 1', 'Шевченко Тарас', '', 'Нагорода "За відвагу", I ступеня', '0', ''], fgetcsv($stream, 0, ';', '"', ''));
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
}
