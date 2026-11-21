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
}
