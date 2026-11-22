<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Awardee;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SearchTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_empty_query_returns_empty_data(): void
    {
        $response = $this->getJson(route('search.indexJson', ['q' => '']));

        $response->assertOk();
        /** @var array{data: array<int, array<string, mixed>>, pagination: array{total: int, last_page: int, current_page: int}} $data */
        $data = $response->json();

        $this->assertSame([], $data['data']);
        $this->assertSame(0, $data['pagination']['total']);
        $this->assertSame(1, $data['pagination']['last_page']);
        $this->assertSame(1, $data['pagination']['current_page']);
    }

    #[Test]
    public function test_search_returns_matching_awardees(): void
    {
        $order = Order::query()->create([
            'number' => '100/2025',
            'document_url' => 'https://example.com/100',
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'ІВАНЕНКО ІВАН',
            'surname' => 'ІВАНЕНКО',
            'given_name' => 'ІВАН',
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'ПЕТРЕНКО ПЕТРО',
            'surname' => 'ПЕТРЕНКО',
            'given_name' => 'ПЕТРО',
        ]);

        $response = $this->getJson('/search/json?q=ІВАНЕНКО');

        $response->assertOk();
        /** @var array{data: array<int, array<string, mixed>>, pagination: array{total: int}} $data */
        $data = $response->json();

        $this->assertSame(1, $data['pagination']['total']);
        $this->assertSame('ІВАНЕНКО ІВАН', $data['data'][0]['full_name']);
    }

    #[Test]
    public function test_search_lowercase_query_matches_uppercase_data(): void
    {
        $order = Order::query()->create([
            'number' => '100/2025',
            'document_url' => 'https://example.com/100',
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'ІВАНЕНКО ІВАН',
            'surname' => 'ІВАНЕНКО',
            'given_name' => 'ІВАН',
        ]);

        // Lowercase Ukrainian query should match uppercase data via mb_lower
        $response = $this->getJson(route('search.indexJson', ['q' => 'іван']));

        $response->assertOk();
        /** @var array{data: array<int, array<string, mixed>>, pagination: array{total: int}} $data */
        $data = $response->json();

        $this->assertSame(1, $data['pagination']['total']);
        $this->assertSame('ІВАНЕНКО ІВАН', $data['data'][0]['full_name']);
    }

    #[Test]
    public function test_search_pagination_works_correctly(): void
    {
        $order = Order::query()->create([
            'number' => '200/2025',
            'document_url' => 'https://example.com/200',
            'parsed_at' => now(),
        ]);

        // Create multiple awardees with matching names
        for ($i = 1; $i <= 5; $i++) {
            Awardee::query()->create([
                'order_id' => $order->id,
                'full_name' => "ТЕСТОВИЙ ПЕРСОНА {$i}",
                'surname' => 'ТЕСТОВИЙ',
                'given_name' => "ПЕРСОНА {$i}",
            ]);
        }

        $response = $this->getJson('/search/json?q=ТЕСТОВИЙ&perPage=2&page=1');

        $response->assertOk();
        /** @var array{data: array<int, array<string, mixed>>, pagination: array{total: int, last_page: int, per_page: int}} $data */
        $data = $response->json();

        $this->assertSame(5, $data['pagination']['total']);
        $this->assertSame(3, $data['pagination']['last_page']);
        $this->assertSame(2, $data['pagination']['per_page']);
        $this->assertCount(2, $data['data']);

        // Page 2
        $response2 = $this->getJson('/search/json?q=ТЕСТОВИЙ&perPage=2&page=2');

        $response2->assertOk();
        /** @var array{data: array<int, array<string, mixed>>} $data2 */
        $data2 = $response2->json();
        $this->assertCount(2, $data2['data']);
    }

    #[Test]
    public function test_search_ignores_deleted_orders(): void
    {
        $order = Order::query()->create([
            'number' => '300/2025',
            'document_url' => 'https://example.com/300',
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'ВИДАЛЕНИЙ ПЕРСОНА',
            'surname' => 'ВИДАЛЕНИЙ',
            'given_name' => 'ПЕРСОНА',
        ]);

        $order->delete();

        $response = $this->getJson('/search/json?q=ВИДАЛЕНИЙ');

        $response->assertOk();
        /** @var array{data: array<int, array<string, mixed>>, pagination: array{total: int}} $data */
        $data = $response->json();

        $this->assertSame(0, $data['pagination']['total']);
        $this->assertSame([], $data['data']);
    }
}
