<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ParseDecreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_decree_is_parsed_and_stored(): void
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

        $response = $this->post('/parse', [
            'mode' => 'single',
            'document_url' => 'https://example.com/decree',
        ]);

        $response->assertRedirect(route('parse.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('orders', [
            'document_url' => 'https://example.com/decree',
            'number' => '425/2025',
        ]);

        $this->assertDatabaseHas('awardees', [
            'full_name' => 'БАКАЛ Олександр Вікторович',
            'award' => 'орден «За мужність» III ступеня',
        ]);
    }

    public function test_consecutive_award_segments_keep_names_and_ranks_with_their_awards(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html><head><title>Указ № 426/2025</title></head><body>
<p>Нагородити орденом «За мужність» III ступеня БАКАЛА Олександра Вікторовича – солдата.</p>
<p>Нагородити медаллю «За військову службу Україні» КОВАЛЕНКА Івана Петровича – старшого сержанта.</p>
</body></html>
HTML;

        Http::fake([
            'https://example.com/decree-with-two-awards' => Http::response($html, 200),
        ]);

        $response = $this->post('/parse', [
            'mode' => 'single',
            'document_url' => 'https://example.com/decree-with-two-awards',
        ]);

        $response->assertRedirect(route('parse.index'));

        $this->assertDatabaseCount('awardees', 2);
        $this->assertDatabaseHas('awardees', [
            'full_name' => 'БАКАЛ Олександр Вікторович',
            'award' => 'орден «За мужність» III ступеня',
            'rank' => 'солдат',
        ]);
        $this->assertDatabaseHas('awardees', [
            'full_name' => 'КОВАЛЕНКО Іван Петрович',
            'award' => 'медаль «За військову службу Україні»',
            'rank' => 'старший сержант',
        ]);
    }
}
