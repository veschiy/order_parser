<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Awardee;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ParseDecreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_decree_is_parsed_and_stored(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html><head><title>Указ № 425/2025</title></head><body>
<p>Нагородити орденом «За мужність» III ступеня БАКАЛА Олександра Вікторовича – солдата.</p>
</body></html>
HTML;

        Http::fake([
            'https://example.com/decree' => Http::response($html, 200),
        ]);

        $response = $this->post('/parse', [
            'mode' => 'single',
            'document_url' => 'https://example.com/decree',
        ]);

        $response->assertRedirect(route('parse.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('orders', [
            'document_url' => 'https://example.com/decree',
            'number' => '425/2025',
        ]);

        $this->assertDatabaseHas('awardees', [
            'full_name' => 'БАКАЛ Олександр Вікторович',
            'award' => 'орденом «За мужність» III ступеня',
        ]);
    }

    public function test_orders_and_awardees_can_be_listed(): void
    {
        $order = Order::query()->create([
            'document_url' => 'https://example.com/order',
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'БАКАЛ Олександр Вікторович',
        ]);

        $this->get('/orders/'.$order->id)
            ->assertOk()
            ->assertSee('БАКАЛ Олександр Вікторович');
    }
}
