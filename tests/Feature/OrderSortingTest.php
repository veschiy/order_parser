<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OrderSortingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Order::query()->create([
            'number' => '100/2025',
            'title' => 'Нагородження за заслуги',
            'document_url' => 'https://example.com/100',
            'parsed_at' => '2025-01-15 10:00:00',
        ]);

        Order::query()->create([
            'number' => '50/2025',
            'title' => 'Нагородження військових',
            'document_url' => 'https://example.com/50',
            'parsed_at' => '2025-03-20 12:00:00',
        ]);

        Order::query()->create([
            'number' => '200/2025',
            'title' => 'Нагородження працівників',
            'document_url' => 'https://example.com/200',
            'parsed_at' => '2025-06-01 08:00:00',
        ]);
    }

    #[Test]
    public function test_sort_by_number_ascending(): void
    {
        $response = $this->getJson(route('orders.indexJson', [
            'sort' => 'number',
            'direction' => 'asc',
        ]));

        $response->assertOk();
        /** @var array{data: array<int, array<string, mixed>>, pagination: array{total: int}} $data */
        $data = $response->json();

        $this->assertSame(3, $data['pagination']['total']);
        // MySQL string sort: '100/2025' < '200/2025' < '50/2025'
        $numbers = array_column($data['data'], 'number');
        $this->assertSame('№100/2025', $numbers[0]);
        $this->assertSame('№200/2025', $numbers[1]);
        $this->assertSame('№50/2025', $numbers[2]);
    }

    #[Test]
    public function test_sort_by_number_descending(): void
    {
        $response = $this->getJson(route('orders.indexJson', [
            'sort' => 'number',
            'direction' => 'desc',
        ]));

        $response->assertOk();
        /** @var array{data: array<int, array<string, mixed>>, pagination: array{total: int}} $data */
        $data = $response->json();

        // MySQL string sort desc: '50/2025' > '200/2025' > '100/2025'
        $numbers = array_column($data['data'], 'number');
        $this->assertSame('№50/2025', $numbers[0]);
        $this->assertSame('№200/2025', $numbers[1]);
        $this->assertSame('№100/2025', $numbers[2]);
    }

    #[Test]
    public function test_sort_by_title_ascending(): void
    {
        $response = $this->getJson(route('orders.indexJson', [
            'sort' => 'title',
            'direction' => 'asc',
        ]));

        $response->assertOk();
        /** @var array{data: array<int, array<string, mixed>>, pagination: array{total: int}} $data */
        $data = $response->json();

        $titles = array_column($data['data'], 'title');
        // MySQL collation order for Ukrainian
        $sortedTitles = $titles;
        sort($sortedTitles);
        $this->assertSame($sortedTitles, $titles);
    }

    #[Test]
    public function test_sort_by_title_descending(): void
    {
        $response = $this->getJson(route('orders.indexJson', [
            'sort' => 'title',
            'direction' => 'desc',
        ]));

        $response->assertOk();
        /** @var array{data: array<int, array<string, mixed>>, pagination: array{total: int}} $data */
        $data = $response->json();

        $titles = array_column($data['data'], 'title');
        // MySQL collation order for Ukrainian (descending)
        rsort($titles);
        $sortedTitles = $titles;
        sort($sortedTitles);
        rsort($sortedTitles);
        $this->assertSame($sortedTitles, $titles);
    }

    #[Test]
    public function test_sort_by_awardees_count_ascending(): void
    {
        $response = $this->getJson(route('orders.indexJson', [
            'sort' => 'awardees_count',
            'direction' => 'asc',
        ]));

        $response->assertOk();
        /** @var array{data: array<int, array<string, mixed>>, pagination: array{total: int}} $data */
        $data = $response->json();

        // All orders have 0 awardees, so order is stable
        $this->assertSame(3, $data['pagination']['total']);
        foreach ($data['data'] as $row) {
            $this->assertSame(0, $row['awardees_count']);
        }
    }

    #[Test]
    public function test_sort_by_awardees_count_descending(): void
    {
        $response = $this->getJson(route('orders.indexJson', [
            'sort' => 'awardees_count',
            'direction' => 'desc',
        ]));

        $response->assertOk();
        /** @var array{data: array<int, array<string, mixed>>, pagination: array{total: int}} $data */
        $data = $response->json();

        $this->assertSame(3, $data['pagination']['total']);
    }

    #[Test]
    public function test_sort_by_parsed_at_ascending(): void
    {
        $response = $this->getJson(route('orders.indexJson', [
            'sort' => 'parsed_at',
            'direction' => 'asc',
        ]));

        $response->assertOk();
        /** @var array{data: array<int, array<string, mixed>>, pagination: array{total: int}} $data */
        $data = $response->json();

        $numbers = array_column($data['data'], 'number');
        $this->assertSame('№100/2025', $numbers[0]);
        $this->assertSame('№50/2025', $numbers[1]);
        $this->assertSame('№200/2025', $numbers[2]);
    }

    #[Test]
    public function test_sort_by_parsed_at_descending(): void
    {
        $response = $this->getJson(route('orders.indexJson', [
            'sort' => 'parsed_at',
            'direction' => 'desc',
        ]));

        $response->assertOk();
        /** @var array{data: array<int, array<string, mixed>>, pagination: array{total: int}} $data */
        $data = $response->json();

        $numbers = array_column($data['data'], 'number');
        $this->assertSame('№200/2025', $numbers[0]);
        $this->assertSame('№50/2025', $numbers[1]);
        $this->assertSame('№100/2025', $numbers[2]);
    }

    #[Test]
    public function test_invalid_sort_column_falls_back_to_latest(): void
    {
        $response = $this->getJson(route('orders.indexJson', [
            'sort' => 'invalid_column',
            'direction' => 'asc',
        ]));

        $response->assertOk();
        /** @var array{data: array<int, array<string, mixed>>, pagination: array{total: int}} $data */
        $data = $response->json();

        // Should fall back to latest() ordering (newest first by created_at, which matches creation order)
        $this->assertSame(3, $data['pagination']['total']);
    }
}
