<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Awardee;
use App\Models\Order;
use App\Models\ParseRun;
use App\Services\Parsers\AwardsParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class DuplicateParseTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_parse_does_not_duplicate_awardees(): void
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

        app(AwardsParserService::class)->parseSingle('https://example.com/decree');
        app(AwardsParserService::class)->parseSingle('https://example.com/decree');

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, Awardee::query()->count());
        $this->assertSame(2, ParseRun::query()->count());
    }
}
