<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Awardee;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AwardeeValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_full_name_is_rejected(): void
    {
        $order = Order::query()->create([
            'document_url' => 'https://example.com/order',
            'parsed_at' => now(),
        ]);
        $awardee = Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'БАКАЛ Олександр Вікторович',
        ]);

        $this->patchJson('/awardees/'.$awardee->id, [
            'full_name' => '',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('awardees', [
            'id' => $awardee->id,
            'full_name' => 'БАКАЛ Олександр Вікторович',
        ]);
    }

    public function test_valid_full_name_is_trimmed_and_stored(): void
    {
        $order = Order::query()->create([
            'document_url' => 'https://example.com/order',
            'parsed_at' => now(),
        ]);
        $awardee = Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'БАКАЛ Олександр Вікторович',
        ]);

        $this->patchJson('/awardees/'.$awardee->id, [
            'full_name' => '  НОВЕ ІМ Я  ',
        ])->assertOk();

        $this->assertDatabaseHas('awardees', [
            'id' => $awardee->id,
            'full_name' => 'НОВЕ ІМ Я',
        ]);
    }
}
