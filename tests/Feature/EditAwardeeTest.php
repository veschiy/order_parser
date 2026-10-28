<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Awardee;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EditAwardeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_awardee_name_can_be_edited(): void
    {
        $order = Order::query()->create(['document_url' => 'https://example.com/order', 'parsed_at' => now()]);
        $awardee = Awardee::query()->create(['order_id' => $order->id, 'full_name' => "СТАРЕ ІМ'Я"]);

        $response = $this->patchJson('/awardees/'.$awardee->id, [
            'full_name' => "НОВЕ ІМ'Я",
        ]);

        $response->assertOk()->assertJson(['full_name' => "НОВЕ ІМ'Я"]);
        $this->assertDatabaseHas('awardees', ['id' => $awardee->id, 'full_name' => "НОВЕ ІМ'Я"]);
    }
}
