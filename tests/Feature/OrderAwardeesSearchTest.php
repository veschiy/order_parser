<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Awardee;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OrderAwardeesSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $order = Order::query()->create([
            'number' => '107/2025',
            'title' => 'Нагородження за заслуги',
            'document_url' => 'https://example.com/107',
            'parsed_at' => '2025-01-15 10:00:00',
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'Шовковий Олександр Володимирович',
            'rank' => 'генерал',
            'award' => 'орден «За мужність» III ступеня',
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'Бандерівець Іван Миколайович',
            'rank' => 'полковник',
            'award' => 'медаль «За заслуги»',
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'Мельник Петро Іванович',
            'rank' => 'генерал',
            'award' => 'орден «За мужність» III ступеня',
        ]);
    }

    #[Test]
    public function test_awardee_list_returns_all_without_query(): void
    {
        $response = $this->getJson(route('orders.awardeesJson', ['id' => 1]));

        $response->assertOk();
        /** @var array{data: array<int, array<string, mixed>>, pagination: array{total: int}} $data */
        $data = $response->json();

        $this->assertSame(3, $data['pagination']['total']);
        $this->assertCount(3, $data['data']);
    }

    #[Test]
    public function test_awardee_list_filters_by_full_name_substring(): void
    {
        $response = $this->getJson(route('orders.awardeesJson', ['id' => 1, 'q' => 'Олександр']));

        $response->assertOk();
        /** @var array{data: array<int, array<string, mixed>>, pagination: array{total: int}} $data */
        $data = $response->json();

        $this->assertSame(1, $data['pagination']['total']);
        $this->assertCount(1, $data['data']);
        $this->assertSame('Шовковий Олександр Володимирович', $data['data'][0]['full_name']);
    }

    #[Test]
    public function test_awardee_list_filters_by_rank(): void
    {
        $response = $this->getJson(route('orders.awardeesJson', ['id' => 1, 'q' => 'генерал']));

        $response->assertOk();
        /** @var array{data: array<int, array<string, mixed>>, pagination: array{total: int}} $data */
        $data = $response->json();

        // Two awardees hold the «генерал» rank.
        $this->assertSame(2, $data['pagination']['total']);
        $names = array_column($data['data'], 'full_name');
        sort($names);
        $this->assertSame(['Мельник Петро Іванович', 'Шовковий Олександр Володимирович'], $names);
    }

    #[Test]
    public function test_awardee_list_filters_by_award(): void
    {
        $response = $this->getJson(route('orders.awardeesJson', ['id' => 1, 'q' => 'мужність']));

        $response->assertOk();
        /** @var array{data: array<int, array<string, mixed>>, pagination: array{total: int}} $data */
        $data = $response->json();

        // Both «орден» awardees carry the «За мужність» award.
        $this->assertSame(2, $data['pagination']['total']);
    }

    #[Test]
    public function test_awardee_list_matches_any_searchable_field(): void
    {
        $response = $this->getJson(route('orders.awardeesJson', ['id' => 1, 'q' => 'Бандерівець']));

        $response->assertOk();
        /** @var array{data: array<int, array<string, mixed>>, pagination: array{total: int}} $data */
        $data = $response->json();

        // Match on the surname portion of a multi-word full name.
        $this->assertSame(1, $data['pagination']['total']);
        $this->assertSame('Бандерівець Іван Миколайович', $data['data'][0]['full_name']);
    }

    #[Test]
    public function test_awardee_list_filter_respects_pagination_total(): void
    {
        // Request a page beyond the last valid one (perPage=1 over 2 matches => pages 1–2).
        // Rows should be empty while pagination metadata still reflects the filtered set.
        $response = $this->getJson(route('orders.awardeesJson', ['id' => 1,
            'q' => 'генерал',
            'perPage' => 1,
            'page' => 3,
        ]));

        $response->assertOk();
        /** @var array{data: array<int, array<string, mixed>>, pagination: array{total: int, last_page: int}} $data */
        $data = $response->json();

        // The total reflects the filtered set (2), not the full order (3).
        $this->assertSame(2, $data['pagination']['total']);
        $this->assertSame(2, $data['pagination']['last_page']);
        $this->assertCount(0, $data['data']);
    }

    #[Test]
    public function test_awardee_list_filter_with_no_matches_returns_empty(): void
    {
        $response = $this->getJson(route('orders.awardeesJson', ['id' => 1, 'q' => "неіснуюче_ім'я"]));

        $response->assertOk();
        /** @var array{data: array<int, array<string, mixed>>, pagination: array{total: int}} $data */
        $data = $response->json();

        $this->assertSame(0, $data['pagination']['total']);
        $this->assertCount(0, $data['data']);
    }
}
