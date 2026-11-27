<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Awardee;
use App\Services\Documents\DecreeParser;
use App\Services\Documents\ParsedAwardee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ParseRealDecreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_decree_fixture_parses_all_awardees(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/decree_107.html')) ?: '';

        Http::fake([
            'https://example.com/decree' => Http::response($html, 200),
        ]);

        $decree = (new DecreeParser)->parse('https://example.com/decree');

        $this->assertSame('УКАЗ ПРЕЗИДЕНТА УКРАЇНИ №107/2026', $decree->title);
        $this->assertSame('107/2026', $decree->number);
        $this->assertGreaterThan(340, count($decree->awardees));

        $names = array_map(
            static fn (ParsedAwardee $awardee): string => $awardee->fullName,
            $decree->awardees,
        );

        $this->assertContains('АРЕНДАРУК Михайло Володимирович', $names);
        $this->assertContains('КУРКО Ірина Вікторівна', $names);
        $this->assertContains('МЮЛЛІ Дженніфер Анне', $names);

        $awardeesByName = array_combine($names, $decree->awardees);

        $this->assertSame('капітан', $awardeesByName['АРЕНДАРУК Михайло Володимирович']->rank);
        $this->assertStringStartsWith('орден', $awardeesByName['АРЕНДАРУК Михайло Володимирович']->award ?? '');
        $this->assertSame('посмертно', $awardeesByName['БІЛАН Олександр Григорович']->positionNote);
    }

    public function test_rank_does_not_include_the_following_award_segment(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html><body>
<p>Нагородити орденом Богдана Хмельницького ІІ ступеня СЕРГІЄНКА Олександра Адамовича (посмертно) — підполковника.</p>
<p>Нагородити орденом Богдана Хмельницького І ступеня ІВАНЕНКА Івана Івановича — майора.</p>
</body></html>
HTML;

        Http::fake([
            'https://example.com/decree' => Http::response($html, 200),
        ]);

        $decree = (new DecreeParser)->parse('https://example.com/decree');

        $this->assertCount(2, $decree->awardees);
        $this->assertSame('СЕРГІЄНКО Олександр Адамович', $decree->awardees[0]->fullName);
        $this->assertSame('підполковник', $decree->awardees[0]->rank);
        $this->assertSame('орден Богдана Хмельницького ІІ ступеня', $decree->awardees[0]->award);
        $this->assertSame('орден Богдана Хмельницького І ступеня', $decree->awardees[1]->award);
        $this->assertSame('майор', $decree->awardees[1]->rank);
    }

    public function test_reparsing_existing_decree_reports_its_stored_awardees(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/decree_107.html')) ?: '';

        Http::fake([
            'https://example.com/decree' => Http::response($html, 200),
        ]);

        $this->post('/parse', [
            'mode' => 'single',
            'document_url' => 'https://example.com/decree',
        ]);

        $response = $this->post('/parse', [
            'mode' => 'single',
            'document_url' => 'https://example.com/decree',
        ]);

        $awardeesCount = Awardee::query()->count();
        $this->assertGreaterThan(100, $awardeesCount);
        $response->assertSessionHas('status', sprintf(
            'Режим: single. Знайдено: 1, розібрано: 1, указів: 1, нагороджених: %d.',
            $awardeesCount,
        ));
    }
}
