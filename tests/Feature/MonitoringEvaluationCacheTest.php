<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Awardee;
use App\Models\MonitoringRecord;
use App\Models\Order;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class MonitoringEvaluationCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'database']);
        Cache::flush();
    }

    public function test_reuses_full_evaluations_across_filtered_pages_and_match_options(): void
    {
        $order = Order::query()->create([
            'number' => '901/2026',
            'document_url' => 'https://example.com/order-901',
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'Кешування Олена Петрівна',
        ]);

        MonitoringRecord::query()->create(['full_name' => 'Кешування Олена Петрівна']);
        MonitoringRecord::query()->create(['full_name' => 'Друга Особа']);

        $awardeeSelectQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$awardeeSelectQueries): void {
            $sql = strtolower(ltrim($query->sql));

            if (str_starts_with($sql, 'select') && str_contains($sql, 'awardees')) {
                $awardeeSelectQueries++;
            }
        });

        $filteredPage = $this->getJson(route('monitoring.indexJson', [
            'q' => 'Друга',
            'perPage' => 1,
        ]));
        $filteredPage->assertOk();
        $filteredPage->assertJsonPath('pagination.total', 1);
        $filteredPage->assertJsonPath('data.0.full_name', 'Друга Особа');

        $matchOptions = $this->getJson(route('monitoring.distinctJson', [
            'column' => 'matches',
            'q' => 'Кешування',
        ]));
        $matchOptions->assertOk();
        $matchOptions->assertJsonPath('values', ['№901/2026', '__empty__']);

        $secondPage = $this->getJson(route('monitoring.indexJson', [
            'page' => 2,
            'perPage' => 1,
        ]));
        $secondPage->assertOk();
        $secondPage->assertJsonPath('pagination.total', 2);
        $secondPage->assertJsonPath('data.0.full_name', 'Друга Особа');

        $this->assertSame(1, $awardeeSelectQueries);
    }

    public function test_refreshes_cached_evaluations_when_a_monitoring_record_changes(): void
    {
        $record = MonitoringRecord::query()->create(['full_name' => 'Початкове Ім’я']);

        $this->getJson(route('monitoring.indexJson'))
            ->assertOk()
            ->assertJsonPath('data.0.full_name', 'Початкове Ім’я');

        $this->put(route('monitoring.update', ['id' => $record->id]), [
            'full_name' => 'Оновлене Ім’я',
        ])->assertRedirect(route('monitoring.index'));

        $this->getJson(route('monitoring.indexJson'))
            ->assertOk()
            ->assertJsonPath('data.0.full_name', 'Оновлене Ім’я');
    }

    public function test_includes_manually_added_monitoring_records_after_the_cache_is_warmed(): void
    {
        MonitoringRecord::query()->create(['full_name' => 'Початкова Особа']);

        $this->getJson(route('monitoring.indexJson'))
            ->assertOk()
            ->assertJsonPath('pagination.total', 1);

        $this->post(route('monitoring.store'), [
            'full_name' => 'Нова Особа',
        ])->assertRedirect(route('monitoring.index'));

        $this->getJson(route('monitoring.indexJson'))
            ->assertOk()
            ->assertJsonPath('pagination.total', 2)
            ->assertJsonFragment(['full_name' => 'Нова Особа']);
    }

    public function test_includes_imported_monitoring_records_after_the_cache_is_warmed(): void
    {
        MonitoringRecord::query()->create(['full_name' => 'Початкова Особа']);

        $this->getJson(route('monitoring.indexJson'))
            ->assertOk()
            ->assertJsonPath('pagination.total', 1);

        $csvContent = <<<'CSV'
Аркуш,ПІБ,ІПН,Дата подання,Нагорода
1,Імпортована Особа,1234567890,2026-10-12,орден
CSV;
        $file = UploadedFile::fake()->createWithContent('monitoring.csv', $csvContent);

        $this->post(route('monitoring.import'), [
            'csv_file' => $file,
        ])->assertRedirect(route('monitoring.index'));

        $this->getJson(route('monitoring.indexJson'))
            ->assertOk()
            ->assertJsonPath('pagination.total', 2)
            ->assertJsonFragment(['full_name' => 'Імпортована Особа']);
    }

    public function test_refreshes_cached_evaluations_when_a_parsed_awardee_changes(): void
    {
        $order = Order::query()->create([
            'number' => '902/2026',
            'document_url' => 'https://example.com/order-902',
            'parsed_at' => now(),
        ]);

        $awardee = Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'Тестова Особа',
        ]);
        MonitoringRecord::query()->create(['full_name' => 'Тестова Особа']);

        $this->getJson(route('monitoring.indexJson'))
            ->assertOk()
            ->assertJsonPath('data.0.score', 1);

        $this->patchJson(route('awardees.update', ['awardee' => $awardee->id]), [
            'full_name' => 'Інша Особа',
        ])->assertOk();

        $this->getJson(route('monitoring.indexJson'))
            ->assertOk()
            ->assertJsonPath('data.0.score', 0);
    }

    public function test_refreshes_cached_evaluations_when_parsed_data_is_cleared(): void
    {
        $order = Order::query()->create([
            'number' => '904/2026',
            'document_url' => 'https://example.com/order-904',
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'Очищена Особа',
        ]);
        MonitoringRecord::query()->create(['full_name' => 'Очищена Особа']);

        $this->getJson(route('monitoring.indexJson'))
            ->assertOk()
            ->assertJsonPath('data.0.score', 1);

        $this->delete(route('parse.clear'))
            ->assertRedirect(route('parse.index'));

        $this->getJson(route('monitoring.indexJson'))
            ->assertOk()
            ->assertJsonPath('data.0.score', 0);
    }

    public function test_refreshes_cached_evaluations_when_a_parsed_order_is_soft_deleted(): void
    {
        $order = Order::query()->create([
            'number' => '903/2026',
            'document_url' => 'https://example.com/order-903',
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'Іваненко Іван Іванович',
        ]);
        MonitoringRecord::query()->create(['full_name' => 'Іваненко Іван Іванович']);

        $this->getJson(route('monitoring.indexJson'))
            ->assertOk()
            ->assertJsonPath('data.0.score', 1);

        $this->delete(route('orders.destroy', ['id' => $order->id]))
            ->assertRedirect(route('orders.index'));

        $this->getJson(route('monitoring.indexJson'))
            ->assertOk()
            ->assertJsonPath('data.0.score', 0);

        $this->patch(route('orders.restore', ['id' => $order->id]))
            ->assertRedirect(route('orders.show', ['id' => $order->id]));

        $this->getJson(route('monitoring.indexJson'))
            ->assertOk()
            ->assertJsonPath('data.0.score', 1);
    }
}
