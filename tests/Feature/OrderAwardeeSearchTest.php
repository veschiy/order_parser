<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Awardee;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrderAwardeeSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_details_view_renders_the_quick_search_controls_and_current_query(): void
    {
        $order = Order::query()->create([
            'document_url' => 'https://example.com/order',
            'parsed_at' => now(),
        ]);

        $response = $this->get(route('orders.show', [
            'id' => $order->id,
            'q' => 'БАКАЛ',
        ]));

        $response->assertOk();
        $response->assertSeeHtml('id="awardeesSearchForm"');
        $response->assertSeeHtml('id="awardeesTable-count"');
        $response->assertSeeHtml('value="БАКАЛ"');
        $response->assertSee('Відображається:');
    }

    public function test_awardees_can_be_searched_by_name_rank_or_award_within_the_selected_order(): void
    {
        $order = Order::query()->create([
            'document_url' => 'https://example.com/order',
            'parsed_at' => now(),
        ]);

        $nameMatch = Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'БАКАЛ Олександр Вікторович',
            'rank' => 'солдат',
            'award' => 'орден «За мужність»',
        ]);
        $rankMatch = Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'ДОВГАНЬ Петро Петрович',
            'rank' => 'капітан',
            'award' => 'медаль',
        ]);
        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'КРАВЧЕНКО Іван Іванович',
            'rank' => 'лейтенант',
            'award' => 'відзнака',
        ]);

        $otherOrder = Order::query()->create([
            'document_url' => 'https://example.com/other-order',
            'parsed_at' => now(),
        ]);
        Awardee::query()->create([
            'order_id' => $otherOrder->id,
            'full_name' => 'Інша Особа',
            'rank' => 'капітан',
            'award' => 'орден',
        ]);

        foreach ([
            ['query' => 'бакал', 'expectedId' => $nameMatch->id],
            ['query' => 'КАПІТАН', 'expectedId' => $rankMatch->id],
            ['query' => 'МУЖНІСТЬ', 'expectedId' => $nameMatch->id],
        ] as $search) {
            $response = $this->getJson(route('orders.awardeesJson', [
                'id' => $order->id,
                'q' => $search['query'],
            ]));

            $response->assertOk()
                ->assertJsonPath('pagination.total', 1)
                ->assertJsonPath('data.0.id', $search['expectedId']);
        }
    }

    public function test_awardee_search_returns_no_results_when_nothing_matches(): void
    {
        $order = Order::query()->create([
            'document_url' => 'https://example.com/order',
            'parsed_at' => now(),
        ]);
        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'БАКАЛ Олександр Вікторович',
        ]);

        $this->getJson(route('orders.awardeesJson', [
            'id' => $order->id,
            'q' => 'неіснуючий запит',
        ]))
            ->assertOk()
            ->assertJsonPath('pagination.total', 0)
            ->assertJsonPath('data', []);
    }
}
