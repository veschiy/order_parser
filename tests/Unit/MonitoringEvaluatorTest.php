<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Awardee;
use App\Models\MonitoringRecord;
use App\Models\Order;
use App\Services\Monitoring\MonitoringEvaluator;
use App\Services\Names\UkrainianNameNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MonitoringEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private MonitoringEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new MonitoringEvaluator(new UkrainianNameNormalizer);
    }

    public function test_score_is_zero_when_person_name_does_not_match(): void
    {
        $order = Order::query()->create([
            'document_url' => 'https://example.com/order-1',
            'published_at' => '2026-11-10',
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'Шевченко Тарас Григорович',
            'award' => 'орден «За мужність» III ступеня',
        ]);

        $record = MonitoringRecord::query()->create([
            'full_name' => 'Франко Іван Якович',
            'award' => 'орден «За мужність» III ступеня',
            'submission_date' => '2026-11-01',
        ]);

        $evaluation = $this->evaluator->evaluate($record);

        $this->assertSame(0, $evaluation->score);
        $this->assertEmpty($evaluation->matches);
        $this->assertNull($evaluation->bestMatch);
    }

    public function test_score_is_one_when_name_matches_only(): void
    {
        $order = Order::query()->create([
            'document_url' => 'https://example.com/order-1',
            'published_at' => '2026-10-01', // Before submission date
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'БАКАЛ Олександр Вікторович',
            'award' => 'медаль «Захиснику Вітчизни»',
        ]);

        $record = MonitoringRecord::query()->create([
            'full_name' => 'Бакал Олександр Вікторович',
            'award' => 'орден «За мужність» III ступеня',
            'submission_date' => '2026-10-15',
        ]);

        $evaluation = $this->evaluator->evaluate($record);

        $this->assertSame(1, $evaluation->score);
        $this->assertCount(1, $evaluation->matches);
        $this->assertTrue($evaluation->matches[0]->nameMatched);
        $this->assertFalse($evaluation->matches[0]->awardMatched);
        $this->assertFalse($evaluation->matches[0]->dateMatched);
    }

    public function test_score_is_two_when_name_and_award_match_but_date_is_not_after(): void
    {
        $order = Order::query()->create([
            'document_url' => 'https://example.com/order-1',
            'published_at' => '2026-10-01', // Before submission date
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'БАКАЛ Олександр Вікторович',
            'award' => 'орден «За мужність» III ступеня',
        ]);

        $record = MonitoringRecord::query()->create([
            'full_name' => 'Бакал Олександр Вікторович',
            'award' => 'орден «За мужність» III ступеня',
            'submission_date' => '2026-10-15',
        ]);

        $evaluation = $this->evaluator->evaluate($record);

        $this->assertSame(2, $evaluation->score);
        $this->assertCount(1, $evaluation->matches);
        $this->assertTrue($evaluation->matches[0]->nameMatched);
        $this->assertTrue($evaluation->matches[0]->awardMatched);
        $this->assertFalse($evaluation->matches[0]->dateMatched);
    }

    public function test_score_is_two_when_name_matches_and_date_is_after_but_award_does_not_match(): void
    {
        $order = Order::query()->create([
            'document_url' => 'https://example.com/order-1',
            'published_at' => '2026-11-10', // After submission date
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'БАКАЛ Олександр Вікторович',
            'award' => 'медаль «Захиснику Вітчизни»',
        ]);

        $record = MonitoringRecord::query()->create([
            'full_name' => 'Бакал Олександр Вікторович',
            'award' => 'орден «За мужність» III ступеня',
            'submission_date' => '2026-11-01',
        ]);

        $evaluation = $this->evaluator->evaluate($record);

        $this->assertSame(2, $evaluation->score);
        $this->assertCount(1, $evaluation->matches);
        $this->assertTrue($evaluation->matches[0]->nameMatched);
        $this->assertFalse($evaluation->matches[0]->awardMatched);
        $this->assertTrue($evaluation->matches[0]->dateMatched);
    }

    public function test_score_is_three_when_name_and_award_match_and_order_date_is_after_submission(): void
    {
        $order = Order::query()->create([
            'document_url' => 'https://example.com/order-1',
            'published_at' => '2026-11-10', // After submission date
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'БАКАЛ Олександр Вікторович',
            'award' => 'орден «За мужність» III ступеня',
        ]);

        $record = MonitoringRecord::query()->create([
            'full_name' => 'Бакал Олександр Вікторович',
            'award' => 'орден «За мужність» III ступеня',
            'submission_date' => '2026-11-01',
        ]);

        $evaluation = $this->evaluator->evaluate($record);

        $this->assertSame(3, $evaluation->score);
        $this->assertCount(1, $evaluation->matches);
        $this->assertTrue($evaluation->matches[0]->nameMatched);
        $this->assertTrue($evaluation->matches[0]->awardMatched);
        $this->assertTrue($evaluation->matches[0]->dateMatched);
    }

    public function test_soft_deleted_orders_are_ignored(): void
    {
        $order = Order::query()->create([
            'document_url' => 'https://example.com/order-1',
            'published_at' => '2026-11-10',
            'parsed_at' => now(),
        ]);

        $awardee = Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'Бакал Олександр Вікторович',
            'award' => 'орден «За мужність» III ступеня',
        ]);

        $order->delete();

        $record = MonitoringRecord::query()->create([
            'full_name' => 'Бакал Олександр Вікторович',
            'award' => 'орден «За мужність» III ступеня',
            'submission_date' => '2026-11-01',
        ]);

        $evaluation = $this->evaluator->evaluate($record);

        $this->assertSame(0, $evaluation->score);
    }

    public function test_multiple_matching_orders_takes_maximum_score(): void
    {
        $order1 = Order::query()->create([
            'document_url' => 'https://example.com/order-1',
            'published_at' => '2026-10-01',
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order1->id,
            'full_name' => 'Бакал Олександр Вікторович',
            'award' => 'медаль «За військову службу Україні»',
        ]);

        $order2 = Order::query()->create([
            'document_url' => 'https://example.com/order-2',
            'published_at' => '2026-11-10',
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order2->id,
            'full_name' => 'Бакал Олександр Вікторович',
            'award' => 'орден «За мужність» III ступеня',
        ]);

        $record = MonitoringRecord::query()->create([
            'full_name' => 'Бакал Олександр Вікторович',
            'award' => 'орден «За мужність» III ступеня',
            'submission_date' => '2026-11-01',
        ]);

        $evaluation = $this->evaluator->evaluate($record);

        $this->assertSame(3, $evaluation->score);
        $this->assertCount(2, $evaluation->matches);
    }

    public function test_matches_awards_with_various_degrees_and_abbreviations(): void
    {
        $this->assertTrue($this->evaluator->awardsMatch('орден “Б.ХМЕЛЬНИЦЬКОГО” ІІІ ступеня', 'орден Богдана Хмельницького III ступеня'));
        $this->assertTrue($this->evaluator->awardsMatch('орден “За мужність ІІІ ступеня”', 'орден «За мужність» III ступеня'));
        $this->assertTrue($this->evaluator->awardsMatch('орден “За мужність” ІІ ступеня', 'орден «За мужність» II ступеня'));
        $this->assertTrue($this->evaluator->awardsMatch('нагрудний знак  “Золотий хрест”', 'нагрудний знак «Золотий хрест»'));
        $this->assertTrue($this->evaluator->awardsMatch('нагрудний знак “За взірцевість  у військовій службі ІІІ ст”', 'нагрудний знак «За взірцевість у військовій службі» III ступеня'));
    }
}
