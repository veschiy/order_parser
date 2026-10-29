<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Awardee;
use App\Models\Order;
use App\Models\ParseRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EmptyDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_clear_database_removes_all_parsed_data(): void
    {
        $order = Order::query()->create([
            'document_url' => 'https://example.com/decree',
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'Тест Тест Тест',
        ]);

        ParseRun::query()->create([
            'mode' => 'single',
            'source_url' => 'https://example.com/decree',
            'status' => 'finished',
        ]);

        $response = $this->delete(route('parse.clear'));

        $response->assertRedirect(route('parse.index'));
        $this->assertSame(0, Order::withTrashed()->count());
        $this->assertSame(0, Awardee::query()->count());
        $this->assertSame(0, ParseRun::query()->count());
    }
}
