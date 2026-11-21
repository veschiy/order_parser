<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Awardee;
use App\Models\MonitoringRecord;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MonitoringSortingAndFilteringTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Set up test records with diverse values for sorting and filtering.
     */
    protected function setUp(): void
    {
        parent::setUp();

        MonitoringRecord::query()->create([
            'sheet' => '3',
            'full_name' => 'Андрійків Андрій',
            'rank' => 'полковник',
            'submission_date' => '2025-06-15',
            'award' => 'орден',
        ]);

        MonitoringRecord::query()->create([
            'sheet' => '1',
            'full_name' => 'Бондаренко Борис',
            'rank' => 'генерал',
            'submission_date' => '2025-03-10',
            'award' => 'медаль',
        ]);

        MonitoringRecord::query()->create([
            'sheet' => '2',
            'full_name' => 'Василенко Віктор',
            'rank' => 'майор',
            'submission_date' => '2025-09-20',
            'award' => 'орден',
        ]);

        MonitoringRecord::query()->create([
            'sheet' => '1',
            'full_name' => 'Гончар Григорій',
            'rank' => null,
            'submission_date' => '2025-01-05',
            'award' => null,
        ]);

        MonitoringRecord::query()->create([
            'sheet' => null,
            'full_name' => 'Дорошенко Дмитро',
            'rank' => 'генерал',
            'submission_date' => null,
            'award' => 'медаль',
        ]);
    }

    /** @test */
    public function test_sort_by_sheet_ascending(): void
    {
        $response = $this->getJson(route('monitoring.indexJson', [
            'sort' => 'sheet',
            'direction' => 'asc',
        ]));

        $response->assertOk();
        $data = $response->json();
        $sheets = array_column($data['data'], 'sheet');

        // Nulls first in MySQL ascending order, then '1', '1', '2', '3'
        $this->assertSame(5, $data['pagination']['total']);
        $this->assertSame('', $sheets[0]); // null sheet → empty string in JSON
        $this->assertSame('1', $sheets[1]);
        $this->assertSame('1', $sheets[2]);
        $this->assertSame('2', $sheets[3]);
        $this->assertSame('3', $sheets[4]);
    }

    /** @test */
    public function test_sort_by_sheet_descending(): void
    {
        $response = $this->getJson(route('monitoring.indexJson', [
            'sort' => 'sheet',
            'direction' => 'desc',
        ]));

        $response->assertOk();
        $data = $response->json();
        $sheets = array_column($data['data'], 'sheet');

        $this->assertSame(5, $data['pagination']['total']);
        $this->assertSame('3', $sheets[0]);
        $this->assertSame('2', $sheets[1]);
        $this->assertSame('1', $sheets[2]);
        $this->assertSame('1', $sheets[3]);
    }

    /** @test */
    public function test_sort_by_full_name_ascending(): void
    {
        $response = $this->getJson(route('monitoring.indexJson', [
            'sort' => 'full_name',
            'direction' => 'asc',
        ]));

        $response->assertOk();
        $data = $response->json();
        $names = array_column($data['data'], 'full_name');

        $this->assertSame(5, $data['pagination']['total']);
        $this->assertSame('Андрійків Андрій', $names[0]);
        $this->assertSame('Бондаренко Борис', $names[1]);
        $this->assertSame('Василенко Віктор', $names[2]);
        $this->assertSame('Гончар Григорій', $names[3]);
        $this->assertSame('Дорошенко Дмитро', $names[4]);
    }

    /** @test */
    public function test_sort_by_full_name_descending(): void
    {
        $response = $this->getJson(route('monitoring.indexJson', [
            'sort' => 'full_name',
            'direction' => 'desc',
        ]));

        $response->assertOk();
        $data = $response->json();
        $names = array_column($data['data'], 'full_name');

        $this->assertSame(5, $data['pagination']['total']);
        $this->assertSame('Дорошенко Дмитро', $names[0]);
        $this->assertSame('Гончар Григорій', $names[1]);
        $this->assertSame('Василенко Віктор', $names[2]);
        $this->assertSame('Бондаренко Борис', $names[3]);
        $this->assertSame('Андрійків Андрій', $names[4]);
    }

    /** @test */
    public function test_sort_by_submission_date_ascending(): void
    {
        $response = $this->getJson(route('monitoring.indexJson', [
            'sort' => 'submission_date',
            'direction' => 'asc',
        ]));

        $response->assertOk();
        $data = $response->json();
        $dates = array_column($data['data'], 'submission_date');

        $this->assertSame(5, $data['pagination']['total']);
        // Nulls first in MySQL ascending order, then chronological
        $this->assertSame('', $dates[0]); // null submission_date → empty string in JSON
        $this->assertSame('2025-01-05', $dates[1]);
        $this->assertSame('2025-03-10', $dates[2]);
        $this->assertSame('2025-06-15', $dates[3]);
        $this->assertSame('2025-09-20', $dates[4]);
    }

    /** @test */
    public function test_sort_by_submission_date_descending(): void
    {
        $response = $this->getJson(route('monitoring.indexJson', [
            'sort' => 'submission_date',
            'direction' => 'desc',
        ]));

        $response->assertOk();
        $data = $response->json();
        $dates = array_column($data['data'], 'submission_date');

        $this->assertSame(5, $data['pagination']['total']);
        // Newest first, nulls last in descending
        $this->assertSame('2025-09-20', $dates[0]);
        $this->assertSame('2025-06-15', $dates[1]);
        $this->assertSame('2025-03-10', $dates[2]);
        $this->assertSame('2025-01-05', $dates[3]);
        $this->assertSame('', $dates[4]);
    }

    /** @test */
    public function test_sort_by_award_ascending(): void
    {
        $response = $this->getJson(route('monitoring.indexJson', [
            'sort' => 'award',
            'direction' => 'asc',
        ]));

        $response->assertOk();
        $data = $response->json();
        $awards = array_column($data['data'], 'award');

        $this->assertSame(5, $data['pagination']['total']);
        // Nulls first in MySQL ascending order, then alphabetical
        $this->assertSame('', $awards[0]); // null award → empty string in JSON
        $this->assertSame('медаль', $awards[1]);
        $this->assertSame('медаль', $awards[2]);
        $this->assertSame('орден', $awards[3]);
        $this->assertSame('орден', $awards[4]);
    }

    /** @test */
    public function test_sort_by_award_descending(): void
    {
        $response = $this->getJson(route('monitoring.indexJson', [
            'sort' => 'award',
            'direction' => 'desc',
        ]));

        $response->assertOk();
        $data = $response->json();
        $awards = array_column($data['data'], 'award');

        $this->assertSame(5, $data['pagination']['total']);
        // Descending: non-nulls first, then nulls last
        $this->assertSame('орден', $awards[0]);
        $this->assertSame('орден', $awards[1]);
        $this->assertSame('медаль', $awards[2]);
        $this->assertSame('медаль', $awards[3]);
        $this->assertSame('', $awards[4]);
    }

    /** @test */
    public function test_sort_by_score_ascending(): void
    {
        // Create matching orders and awardees for some records to get non-zero scores.
        $order = Order::query()->create([
            'number' => '100/2025',
            'document_url' => 'https://example.com/order-100',
            'published_at' => '2025-07-01',
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'АНДРІЙКІВ АНДРІЙ',
            'award' => 'орден',
        ]);

        $response = $this->getJson(route('monitoring.indexJson', [
            'sort' => 'score',
            'direction' => 'asc',
        ]));

        $response->assertOk();
        $data = $response->json();
        $scores = array_column($data['data'], 'score');

        $this->assertSame(5, $data['pagination']['total']);
        // Scores should be sorted ascending: 0s first, then higher scores
        $this->assertSame(0, $scores[0]);
        $this->assertGreaterThanOrEqual($scores[count($scores) - 2], $scores[count($scores) - 1]);
    }

    /** @test */
    public function test_sort_by_score_descending(): void
    {
        $order = Order::query()->create([
            'number' => '101/2025',
            'document_url' => 'https://example.com/order-101',
            'published_at' => '2025-07-01',
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'БОНДАРЕНКО БОРИС',
            'award' => 'медаль',
        ]);

        $response = $this->getJson(route('monitoring.indexJson', [
            'sort' => 'score',
            'direction' => 'desc',
        ]));

        $response->assertOk();
        $data = $response->json();
        $scores = array_column($data['data'], 'score');

        $this->assertSame(5, $data['pagination']['total']);
        // Scores should be sorted descending: higher scores first
        $this->assertGreaterThan(0, $scores[0]);
        $this->assertLessThanOrEqual($scores[0], $scores[1]);
    }

    /** @test */
    public function test_sorting_does_not_break_filtering_by_sheet(): void
    {
        $response = $this->getJson(route('monitoring.indexJson', [
            'sort' => 'full_name',
            'direction' => 'asc',
            'filters' => ['sheet:1'],
        ]));

        $response->assertOk();
        $data = $response->json();

        // Only 2 records have sheet=1
        $this->assertSame(2, $data['pagination']['total']);
        $names = array_column($data['data'], 'full_name');
        // Should still be sorted by full_name
        $this->assertSame('Бондаренко Борис', $names[0]);
        $this->assertSame('Гончар Григорій', $names[1]);
    }

    /** @test */
    public function test_sorting_does_not_break_filtering_by_award(): void
    {
        $response = $this->getJson(route('monitoring.indexJson', [
            'sort' => 'submission_date',
            'direction' => 'desc',
            'filters' => ['award:орден'],
        ]));

        $response->assertOk();
        $data = $response->json();

        // Only 2 records have award=орден
        $this->assertSame(2, $data['pagination']['total']);
        $dates = array_column($data['data'], 'submission_date');
        // Should still be sorted by submission_date desc
        $this->assertSame('2025-09-20', $dates[0]);
        $this->assertSame('2025-06-15', $dates[1]);
    }

    /** @test */
    public function test_sorting_does_not_break_filtering_by_rank(): void
    {
        $response = $this->getJson(route('monitoring.indexJson', [
            'sort' => 'full_name',
            'direction' => 'asc',
            'filters' => ['rank:генерал'],
        ]));

        $response->assertOk();
        $data = $response->json();

        // Only 2 records have rank=генерал
        $this->assertSame(2, $data['pagination']['total']);
        $names = array_column($data['data'], 'full_name');
        $this->assertSame('Бондаренко Борис', $names[0]);
        $this->assertSame('Дорошенко Дмитро', $names[1]);
    }

    /** @test */
    public function test_filtering_does_not_break_sorting(): void
    {
        // Verify that applying a filter preserves the sort order
        $response = $this->getJson(route('monitoring.indexJson', [
            'sort' => 'sheet',
            'direction' => 'desc',
            'filters' => ['award:орден'],
        ]));

        $response->assertOk();
        $data = $response->json();

        $this->assertSame(2, $data['pagination']['total']);
        $sheets = array_column($data['data'], 'sheet');
        // Should be sorted desc: '3' before '2'
        $this->assertSame('3', $sheets[0]);
        $this->assertSame('2', $sheets[1]);
    }

    /** @test */
    public function test_sorting_with_multiple_filters(): void
    {
        $response = $this->getJson(route('monitoring.indexJson', [
            'sort' => 'full_name',
            'direction' => 'asc',
            'filters' => ['sheet:1', 'award:медаль'],
        ]));

        $response->assertOk();
        $data = $response->json();

        // Only 1 record: sheet=1 AND award=медаль (Бондаренко Борис)
        $this->assertSame(1, $data['pagination']['total']);
        $this->assertSame('Бондаренко Борис', $data['data'][0]['full_name']);
    }

    /** @test */
    public function test_search_with_sorting(): void
    {
        $response = $this->getJson(route('monitoring.indexJson', [
            'q' => 'борис',
            'sort' => 'full_name',
            'direction' => 'desc',
        ]));

        $response->assertOk();
        $data = $response->json();

        $this->assertSame(1, $data['pagination']['total']);
        $this->assertSame('Бондаренко Борис', $data['data'][0]['full_name']);
    }

    /** @test */
    public function test_search_with_filtering(): void
    {
        $response = $this->getJson(route('monitoring.indexJson', [
            'q' => 'орден',
            'filters' => ['sheet:3'],
        ]));

        $response->assertOk();
        $data = $response->json();

        // Search for "орден" matches 2 records, but filter sheet:3 narrows to 1
        $this->assertSame(1, $data['pagination']['total']);
        $this->assertSame('Андрійків Андрій', $data['data'][0]['full_name']);
    }

    /** @test */
    public function test_search_with_sorting_and_filtering(): void
    {
        // Search only covers full_name, tax_id, award, sheet — NOT rank.
        // So searching "орден" (award) + filtering sheet:3 should return 1 record.
        $response = $this->getJson(route('monitoring.indexJson', [
            'q' => 'орден',
            'sort' => 'full_name',
            'direction' => 'asc',
            'filters' => ['sheet:3'],
        ]));

        $response->assertOk();
        $data = $response->json();

        // Search "орден" matches 2 records (Андрійків, Василенко), filter sheet:3 narrows to 1
        $this->assertSame(1, $data['pagination']['total']);
        $this->assertSame('Андрійків Андрій', $data['data'][0]['full_name']);
    }

    /** @test */
    public function test_empty_filter_returns_no_results(): void
    {
        $response = $this->getJson(route('monitoring.indexJson', [
            'filters' => ['sheet:'],
        ]));

        $response->assertOk();
        $data = $response->json();

        $this->assertSame(0, $data['pagination']['total']);
        $this->assertSame([], $data['data']);
    }

    /** @test */
    public function test_empty_filter_with_sorting_returns_no_results(): void
    {
        $response = $this->getJson(route('monitoring.indexJson', [
            'sort' => 'full_name',
            'direction' => 'asc',
            'filters' => ['award:'],
        ]));

        $response->assertOk();
        $data = $response->json();

        $this->assertSame(0, $data['pagination']['total']);
        $this->assertSame([], $data['data']);
    }

    /** @test */
    public function test_empty_sentinel_filter_for_null_values(): void
    {
        $response = $this->getJson(route('monitoring.indexJson', [
            'filters' => ['award:__empty__'],
        ]));

        $response->assertOk();
        $data = $response->json();

        // Only 1 record has null award (Гончар Григорій)
        $this->assertSame(1, $data['pagination']['total']);
        $this->assertSame('Гончар Григорій', $data['data'][0]['full_name']);
    }

    /** @test */
    public function test_empty_sentinel_filter_combined_with_values(): void
    {
        $response = $this->getJson(route('monitoring.indexJson', [
            'filters' => ['award:медаль,__empty__'],
        ]));

        $response->assertOk();
        $data = $response->json();

        // 2 records with медаль + 1 with null award = 3 total
        $this->assertSame(3, $data['pagination']['total']);
    }

    /** @test */
    public function test_score_filter_with_sorting_by_score(): void
    {
        $order = Order::query()->create([
            'number' => '200/2025',
            'document_url' => 'https://example.com/order-200',
            'published_at' => '2025-07-01',
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'АНДРІЙКІВ АНДРІЙ',
            'award' => 'орден',
        ]);

        $response = $this->getJson(route('monitoring.indexJson', [
            'sort' => 'score',
            'direction' => 'desc',
            'filters' => ['score:0'],
        ]));

        $response->assertOk();
        $data = $response->json();

        // Only score=0 records
        foreach ($data['data'] as $row) {
            $this->assertSame(0, $row['score']);
        }
    }

    /** @test */
    public function test_score_filter_with_db_column_sorting(): void
    {
        $order = Order::query()->create([
            'number' => '201/2025',
            'document_url' => 'https://example.com/order-201',
            'published_at' => '2025-07-01',
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'БОНДАРЕНКО БОРИС',
            'award' => 'медаль',
        ]);

        $response = $this->getJson(route('monitoring.indexJson', [
            'sort' => 'full_name',
            'direction' => 'asc',
            'filters' => ['score:0'],
        ]));

        $response->assertOk();
        $data = $response->json();

        // All returned records should have score 0 and be sorted by name
        foreach ($data['data'] as $row) {
            $this->assertSame(0, $row['score']);
        }

        $names = array_column($data['data'], 'full_name');
        $sortedNames = $names;
        sort($sortedNames);
        $this->assertSame($sortedNames, $names);
    }

    /** @test */
    public function test_pagination_with_sorting(): void
    {
        $response = $this->getJson(route('monitoring.indexJson', [
            'sort' => 'full_name',
            'direction' => 'asc',
            'perPage' => 2,
            'page' => 1,
        ]));

        $response->assertOk();
        $data = $response->json();

        $this->assertSame(5, $data['pagination']['total']);
        $this->assertSame(3, $data['pagination']['last_page']);
        $this->assertSame(2, $data['pagination']['per_page']);
        $this->assertSame(2, count($data['data']));
        $this->assertSame('Андрійків Андрій', $data['data'][0]['full_name']);
        $this->assertSame('Бондаренко Борис', $data['data'][1]['full_name']);

        // Page 2
        $response2 = $this->getJson(route('monitoring.indexJson', [
            'sort' => 'full_name',
            'direction' => 'asc',
            'perPage' => 2,
            'page' => 2,
        ]));

        $response2->assertOk();
        $data2 = $response2->json();
        $this->assertSame('Василенко Віктор', $data2['data'][0]['full_name']);
        $this->assertSame('Гончар Григорій', $data2['data'][1]['full_name']);
    }

    /** @test */
    public function test_pagination_with_filtering(): void
    {
        $response = $this->getJson(route('monitoring.indexJson', [
            'filters' => ['award:орден'],
            'perPage' => 1,
            'page' => 1,
        ]));

        $response->assertOk();
        $data = $response->json();

        $this->assertSame(2, $data['pagination']['total']);
        $this->assertSame(2, $data['pagination']['last_page']);
        $this->assertSame(1, count($data['data']));

        // Page 2
        $response2 = $this->getJson(route('monitoring.indexJson', [
            'filters' => ['award:орден'],
            'perPage' => 1,
            'page' => 2,
        ]));

        $response2->assertOk();
        $data2 = $response2->json();
        $this->assertSame(1, count($data2['data']));
    }

    /** @test */
    public function test_pagination_with_sorting_and_filtering(): void
    {
        $response = $this->getJson(route('monitoring.indexJson', [
            'sort' => 'full_name',
            'direction' => 'asc',
            'filters' => ['award:орден'],
            'perPage' => 1,
            'page' => 1,
        ]));

        $response->assertOk();
        $data = $response->json();

        $this->assertSame(2, $data['pagination']['total']);
        $this->assertSame('Андрійків Андрій', $data['data'][0]['full_name']);

        $response2 = $this->getJson(route('monitoring.indexJson', [
            'sort' => 'full_name',
            'direction' => 'asc',
            'filters' => ['award:орден'],
            'perPage' => 1,
            'page' => 2,
        ]));

        $response2->assertOk();
        $data2 = $response2->json();
        $this->assertSame('Василенко Віктор', $data2['data'][0]['full_name']);
    }

    /** @test */
    public function test_unknown_sort_column_ignored(): void
    {
        $response = $this->getJson(route('monitoring.indexJson', [
            'sort' => 'unknown_column',
            'direction' => 'asc',
        ]));

        $response->assertOk();
        $data = $response->json();

        // Should fall back to default ID ordering
        $this->assertSame(5, $data['pagination']['total']);
    }

    /** @test */
    public function test_distinct_values_endpoint_with_search(): void
    {
        $response = $this->getJson(route('monitoring.distinctJson', [
            'column' => 'award',
            'q' => 'орден',
        ]));

        $response->assertOk();
        $data = $response->json();

        // Search for "орден" should only return "орден" as a distinct award value
        $this->assertContains('орден', $data['values']);
        $this->assertContains('__empty__', $data['values']);
        $this->assertNotContains('медаль', $data['values']);
    }

    /** @test */
    public function test_distinct_values_endpoint_for_score(): void
    {
        $response = $this->getJson(route('monitoring.distinctJson', [
            'column' => 'score',
        ]));

        $response->assertOk();
        $data = $response->json();

        $this->assertSame(['0', '1', '2', '3', '4', '__empty__'], $data['values']);
    }

    /** @test */
    public function test_distinct_values_for_unknown_column(): void
    {
        $response = $this->getJson(route('monitoring.distinctJson', [
            'column' => 'nonexistent',
        ]));

        $response->assertOk();
        $data = $response->json();

        $this->assertSame([], $data['values']);
    }

    /** @test */
    public function test_filter_by_submission_date(): void
    {
        // The submission_date is stored as a date, but the filter compares the raw DB value.
        // The JSON endpoint returns dates in Y-m-d format. The filter uses the stored date string.
        $response = $this->getJson(route('monitoring.indexJson', [
            'filters' => ['submission_date:2025-06-15 00:00:00'],
        ]));

        $response->assertOk();
        $data = $response->json();

        $this->assertSame(1, $data['pagination']['total']);
        $this->assertSame('Андрійків Андрій', $data['data'][0]['full_name']);
    }

    /** @test */
    public function test_filter_by_full_name(): void
    {
        $response = $this->getJson(route('monitoring.indexJson', [
            'filters' => ['full_name:Бондаренко Борис'],
        ]));

        $response->assertOk();
        $data = $response->json();

        $this->assertSame(1, $data['pagination']['total']);
        $this->assertSame('Бондаренко Борис', $data['data'][0]['full_name']);
    }

    /** @test */
    public function test_sorting_by_rank_is_not_supported(): void
    {
        // Rank is filterable but not sortable (not in sortableColumns)
        $response = $this->getJson(route('monitoring.indexJson', [
            'sort' => 'rank',
            'direction' => 'asc',
        ]));

        $response->assertOk();
        $data = $response->json();

        // Should fall back to default ID ordering
        $this->assertSame(5, $data['pagination']['total']);
    }
}
