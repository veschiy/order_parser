<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Awardee;
use App\Models\MonitoringRecord;
use App\Models\Order;
use App\Services\Monitoring\MonitoringEvaluation;
use App\Services\Monitoring\MonitoringEvaluator;
use App\Services\Monitoring\MonitoringMatch;
use App\Services\Names\NameNormalizer;
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

    public function test_skips_normalizing_awardees_with_different_name_prefixes(): void
    {
        $order = Order::query()->create([
            'document_url' => 'https://example.com/order-2',
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'Борисенко Олена Василівна',
        ]);

        $record = MonitoringRecord::query()->create([
            'full_name' => 'Бакал Олександр Вікторович',
        ]);

        $normalizer = new class implements NameNormalizer
        {
            public int $normalizationCount = 0;

            public function toNominative(string $name): string
            {
                $this->normalizationCount++;

                return $name;
            }
        };

        $evaluator = new MonitoringEvaluator($normalizer);

        $evaluation = $evaluator->evaluateAll([$record])->first();

        $this->assertInstanceOf(MonitoringEvaluation::class, $evaluation);
        $this->assertSame(0, $evaluation->score);
        $this->assertSame(1, $normalizer->normalizationCount);
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

    public function test_rank_match_adds_one_point_between_name_and_award(): void
    {
        $order = Order::query()->create([
            'document_url' => 'https://example.com/order-1',
            'published_at' => '2026-10-01', // Before submission date → no date match
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'БАКАЛ Олександр Вікторович',
            'rank' => 'генерал',
            'award' => 'медаль «Захиснику Вітчизни»',
        ]);

        $record = MonitoringRecord::query()->create([
            'full_name' => 'Бакал Олександр Вікторович',
            'rank' => 'Генерал', // Different case → still matches (case-insensitive)
            'award' => 'орден «За мужність» III ступеня',
            'submission_date' => '2026-10-15',
        ]);

        $evaluation = $this->evaluator->evaluate($record);

        $this->assertSame(2, $evaluation->score);
        $this->assertCount(1, $evaluation->matches);
        $this->assertTrue($evaluation->matches[0]->nameMatched);
        $this->assertTrue($evaluation->matches[0]->rankMatched);
        $this->assertFalse($evaluation->matches[0]->awardMatched);
        $this->assertFalse($evaluation->matches[0]->dateMatched);
    }

    public function test_rank_mismatch_does_not_add_points(): void
    {
        $order = Order::query()->create([
            'document_url' => 'https://example.com/order-1',
            'published_at' => '2026-10-01', // Before submission date → no date match
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'БАКАЛ Олександр Вікторович',
            'rank' => 'генерал',
            'award' => 'медаль «Захиснику Вітчизни»',
        ]);

        $record = MonitoringRecord::query()->create([
            'full_name' => 'Бакал Олександр Вікторович',
            'rank' => 'полковник', // Different rank → no match
            'award' => 'орден «За мужність» III ступеня',
            'submission_date' => '2026-10-15',
        ]);

        $evaluation = $this->evaluator->evaluate($record);

        $this->assertSame(1, $evaluation->score);
        $this->assertFalse($evaluation->matches[0]->rankMatched);
    }

    public function test_score_is_three_when_name_rank_and_award_match_but_date_does_not(): void
    {
        $order = Order::query()->create([
            'document_url' => 'https://example.com/order-1',
            'published_at' => '2026-10-01', // Before submission date → no date match
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'БАКАЛ Олександр Вікторович',
            'rank' => 'генерал',
            'award' => 'орден «За мужність» III ступеня',
        ]);

        $record = MonitoringRecord::query()->create([
            'full_name' => 'Бакал Олександр Вікторович',
            'rank' => 'Генерал',
            'award' => 'орден «За мужність» III ступеня',
            'submission_date' => '2026-10-15',
        ]);

        $evaluation = $this->evaluator->evaluate($record);

        $this->assertSame(3, $evaluation->score);
        $this->assertTrue($evaluation->matches[0]->nameMatched);
        $this->assertTrue($evaluation->matches[0]->rankMatched);
        $this->assertTrue($evaluation->matches[0]->awardMatched);
        $this->assertFalse($evaluation->matches[0]->dateMatched);
    }

    public function test_score_is_four_when_name_rank_award_and_date_all_match(): void
    {
        $order = Order::query()->create([
            'document_url' => 'https://example.com/order-1',
            'published_at' => '2026-11-10', // After submission date → date match
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'БАКАЛ Олександр Вікторович',
            'rank' => 'генерал',
            'award' => 'орден «За мужність» III ступеня',
        ]);

        $record = MonitoringRecord::query()->create([
            'full_name' => 'Бакал Олександр Вікторович',
            'rank' => 'Генерал',
            'award' => 'орден «За мужність» III ступеня',
            'submission_date' => '2026-11-01',
        ]);

        $evaluation = $this->evaluator->evaluate($record);

        $this->assertSame(4, $evaluation->score);
        $this->assertTrue($evaluation->matches[0]->nameMatched);
        $this->assertTrue($evaluation->matches[0]->rankMatched);
        $this->assertTrue($evaluation->matches[0]->awardMatched);
        $this->assertTrue($evaluation->matches[0]->dateMatched);
    }

    public function test_date_score_requires_a_date_within_six_calendar_months(): void
    {
        $record = new MonitoringRecord([
            'full_name' => 'Бакал Олександр Вікторович',
            'award' => 'орден «За мужність» III ступеня',
            'submission_date' => '2025-08-31',
        ]);

        foreach ([
            ['2025-08-30', 2],
            ['2025-08-31', 2],
            ['2025-09-01', 3],
            ['2026-02-28', 3],
            ['2026-03-01', 2],
        ] as [$publishedAt, $expectedScore]) {
            $order = new Order(['published_at' => $publishedAt]);
            $awardee = new Awardee(['full_name' => $record->full_name, 'award' => $record->award]);
            $awardee->setRelation('order', $order);

            $evaluation = $this->evaluator->evaluate($record, [$awardee]);

            $this->assertSame($expectedScore, $evaluation->score, $publishedAt);
            $this->assertInstanceOf(MonitoringMatch::class, $evaluation->bestMatch);
            $this->assertSame($expectedScore === 3, $evaluation->bestMatch->dateMatched, $publishedAt);
        }
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

    public function test_multiple_matching_orders_takes_maximum_score_and_keeps_first_match_on_tie(): void
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

        $order3 = Order::query()->create([
            'document_url' => 'https://example.com/order-3',
            'published_at' => '2026-11-15',
            'parsed_at' => now(),
        ]);

        Awardee::query()->create([
            'order_id' => $order3->id,
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
        $this->assertCount(3, $evaluation->matches);
        $this->assertSame($order2->id, $evaluation->bestMatch?->awardee->order_id);
    }

    public function test_candidate_awardee_groups_are_reindexed_as_a_list(): void
    {
        $order = Order::query()->create([
            'document_url' => 'https://example.com/order-1',
            'parsed_at' => now(),
        ]);

        $awardee = Awardee::query()->create([
            'order_id' => $order->id,
            'full_name' => 'Бакал Олександр Вікторович',
        ]);

        $method = new \ReflectionMethod($this->evaluator, 'candidateAwardeesByRecord');
        $candidateAwardeesByRecord = $method->invoke(
            $this->evaluator,
            1,
            ['ба ві ол' => [2]],
            ['exact:бакал олександр вікторович' => [2]],
        );

        if (! is_array($candidateAwardeesByRecord)) {
            $this->fail('Expected candidate awardees to be returned as an array.');
        }

        $this->assertSame([0, 1], array_keys($candidateAwardeesByRecord));

        $matchedAwardees = $candidateAwardeesByRecord[1] ?? null;
        if (! is_array($matchedAwardees)) {
            $this->fail('Expected the matching record to have an awardee list.');
        }

        $this->assertSame([$awardee->id], array_keys($matchedAwardees));

        $matchedAwardee = $matchedAwardees[$awardee->id] ?? null;
        if (! $matchedAwardee instanceof Awardee) {
            $this->fail('Expected the matching candidate to be an awardee.');
        }

        $this->assertSame($awardee->id, $matchedAwardee->id);
    }

    public function test_bulk_evaluation_preserves_matching_and_order(): void
    {
        $order = Order::query()->create([
            'document_url' => 'https://example.com/order-1',
            'published_at' => '2026-11-10',
            'parsed_at' => now(),
        ]);

        foreach (['Бакал Олександр Вікторович', 'Франко Іван Якович', 'БАКАЛ  Олександр Вікторович'] as $name) {
            Awardee::query()->create([
                'order_id' => $order->id,
                'full_name' => $name,
                'award' => 'орден «За мужність» III ступеня',
            ]);
        }

        $records = collect([
            new MonitoringRecord(['full_name' => 'Бакал Олександр Вікторович', 'award' => 'орден «За мужність» III ступеня', 'submission_date' => '2026-11-01']),
            new MonitoringRecord(['full_name' => 'Франко Іван Якович']),
            new MonitoringRecord(['full_name' => 'Невідомий Іван Іванович']),
            new MonitoringRecord(['full_name' => '']),
        ]);

        $bulk = $this->evaluator->evaluateAll($records);

        foreach ($records as $index => $record) {
            $individual = $this->evaluator->evaluate($record);
            $this->assertSame($individual->score, $bulk[$index]->score);
            $this->assertSame(
                array_map(fn (MonitoringMatch $match) => $match->awardee->id, $individual->matches),
                array_map(fn (MonitoringMatch $match) => $match->awardee->id, $bulk[$index]->matches),
            );
        }

        $this->assertCount(2, $bulk[0]->matches);
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
