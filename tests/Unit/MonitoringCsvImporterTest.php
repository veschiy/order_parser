<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\MonitoringRecord;
use App\Services\Monitoring\MonitoringCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MonitoringCsvImporterTest extends TestCase
{
    use RefreshDatabase;

    private MonitoringCsvImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new MonitoringCsvImporter;
    }

    public function test_imports_csv_with_comma_and_headers(): void
    {
        $csv = <<<'CSV'
Аркуш,ПІБ,ІПН,Дата подання,Нагорода
1,Шовковий Олександр Володимирович,1234567890,2026-10-15,орден «За мужність» III ступеня
2,Бакал Олександр Вікторович,9876543210,01.11.2026,медаль «Захиснику Вітчизни»
CSV;

        $count = $this->importer->import($csv);

        $this->assertSame(2, $count);
        $this->assertDatabaseCount('monitoring_records', 2);

        $first = MonitoringRecord::query()->where('full_name', 'Шовковий Олександр Володимирович')->firstOrFail();
        $this->assertSame('1', $first->sheet);
        $this->assertNull($first->tax_id); // Tax ID is skipped
        $this->assertSame('2026-10-15', $first->submission_date?->format('Y-m-d'));
        $this->assertSame('орден «За мужність» III ступеня', $first->award);

        $second = MonitoringRecord::query()->where('full_name', 'Бакал Олександр Вікторович')->firstOrFail();
        $this->assertSame('2', $second->sheet);
        $this->assertNull($second->tax_id); // Tax ID is skipped
        $this->assertSame('2026-11-01', $second->submission_date?->format('Y-m-d'));
        $this->assertSame('медаль «Захиснику Вітчизни»', $second->award);
    }

    public function test_imports_csv_with_semicolon_and_without_headers(): void
    {
        $csv = <<<'CSV'
1;Коваль Петро Іванович;15.09.2026;орден Богдана Хмельницького III ступеня
CSV;

        $count = $this->importer->import($csv);

        $this->assertSame(1, $count);
        $record = MonitoringRecord::query()->firstOrFail();
        $this->assertSame('1', $record->sheet);
        $this->assertSame('Коваль Петро Іванович', $record->full_name);
        $this->assertNull($record->tax_id);
        $this->assertSame('2026-09-15', $record->submission_date?->format('Y-m-d'));
        $this->assertSame('орден Богдана Хмельницького III ступеня', $record->award);
    }

    public function test_handles_multiple_dates_and_skips_ipn_and_otrymano(): void
    {
        $csv = <<<'CSV'
Аркуш;ПІБ;ІПН;Дата;Отримано;Нагорода
МОУ;Шевченко Тарас Григорович;3333333333;29.10.2025 01.06.2026;;медаль "За поранення" (ЛЕГКЕ)
ГК;Іваненко Іван Іванович;4444444444;"17.10.2024,
09.05.2025";;нагрудний знак "Золотий хрест"
CSV;

        $count = $this->importer->import($csv);

        $this->assertSame(2, $count);

        $first = MonitoringRecord::query()->where('full_name', 'Шевченко Тарас Григорович')->firstOrFail();
        $this->assertSame('МОУ', $first->sheet);
        $this->assertNull($first->tax_id);
        $this->assertSame('2026-06-01', $first->submission_date?->format('Y-m-d'));
        $this->assertSame('медаль "За поранення" (ЛЕГКЕ)', $first->award);

        $second = MonitoringRecord::query()->where('full_name', 'Іваненко Іван Іванович')->firstOrFail();
        $this->assertSame('ГК', $second->sheet);
        $this->assertNull($second->tax_id);
        $this->assertSame('2025-05-09', $second->submission_date?->format('Y-m-d'));
        $this->assertSame('нагрудний знак "Золотий хрест"', $second->award);
    }

    public function test_handles_bom_and_empty_rows(): void
    {
        $csv = "\xEF\xBB\xBF"."Аркуш,ПІБ,ІПН,Дата подання,Нагорода\n\n1,Іванов Іван,123,,Орден\n   \n";

        $count = $this->importer->import($csv);

        $this->assertSame(1, $count);
        $record = MonitoringRecord::query()->firstOrFail();
        $this->assertSame('Іванов Іван', $record->full_name);
        $this->assertNull($record->submission_date);
    }
}
