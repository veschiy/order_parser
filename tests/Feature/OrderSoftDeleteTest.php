<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Awardee;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrderSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_deleted_order_is_hidden_from_active_search(): void
    {
        $order = Order::query()->create([
            'document_url' => 'https://example.com/order',
            'parsed_at' => now(),
        ]);
        $awardee = Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'БАКАЛ Олександр Вікторович',
        ]);

        $order->delete();

        $this->get('/?q='.urlencode('БАКАЛ'))
            ->assertOk()
            ->assertDontSee('БАКАЛ Олександр Вікторович');
    }

    public function test_restore_makes_order_searchable_again(): void
    {
        $order = Order::query()->create([
            'document_url' => 'https://example.com/order',
            'parsed_at' => now(),
        ]);
        $awardee = Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'БАКАЛ Олександр Вікторович',
        ]);

        $order->delete();

        $this->patch('/orders/'.$order->id.'/restore')
            ->assertRedirect(route('orders.show', $order->id));

        $this->get('/?q='.urlencode('БАКАЛ'))
            ->assertOk()
            ->assertSee('БАКАЛ Олександр Вікторович');
    }

    public function test_soft_deleted_order_can_still_be_opened_and_restored(): void
    {
        $order = Order::query()->create([
            'document_url' => 'https://example.com/order',
            'parsed_at' => now(),
        ]);

        $order->delete();

        $this->get('/orders/'.$order->id)->assertOk();
    }
}
