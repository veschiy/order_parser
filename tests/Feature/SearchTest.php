<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Awardee;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_awarded_person(): void
    {
        $order = Order::query()->create([
            'document_url' => 'https://example.com/order',
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'БАКАЛ Олександр Вікторович',
            'award' => 'орден',
        ]);

        $this->get('/?q=Олександр')
            ->assertOk()
            ->assertSee('БАКАЛ Олександр Вікторович');
    }

    public function test_empty_search_shows_entry_state(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Введіть ім’я');
    }
}
