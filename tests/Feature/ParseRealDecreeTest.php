<?php

declare(strict_types=1);

namespace Tests\Feature;

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

        $this->assertSame('капітана', $awardeesByName['АРЕНДАРУК Михайло Володимирович']->rank);
        $this->assertStringStartsWith('орденом', $awardeesByName['АРЕНДАРУК Михайло Володимирович']->award ?? '');
        $this->assertSame('посмертно', $awardeesByName['БІЛАН Олександр Григорович']->positionNote);
    }
}
