<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Awardee;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrderExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_returns_csv_with_awardee_data(): void
    {
        $order = Order::query()->create([
            'number' => '869/2026',
            'document_url' => 'https://example.com/order',
            'parsed_at' => now(),
        ]);
        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'БАКАЛ Олександр Вікторович',
            'rank' => 'солдат',
            'award' => 'орден «За мужність» III ступеня',
        ]);

        $response = $this->get('/orders/'.$order->id.'/export.csv');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('БАКАЛ Олександр Вікторович', $response->streamedContent());
        $this->assertStringContainsString('869/2026', $response->streamedContent());
    }

    public function test_export_sanitizes_slash_containing_order_number_in_filename(): void
    {
        $order = Order::query()->create([
            'number' => '869/2026',
            'document_url' => 'https://example.com/order',
            'parsed_at' => now(),
        ]);

        $response = $this->get('/orders/'.$order->id.'/export.csv');

        $this->assertStringContainsString('order-869-2026.csv', (string) $response->headers->get('Content-Disposition'));
    }
}
