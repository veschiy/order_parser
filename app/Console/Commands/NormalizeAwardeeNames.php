<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Awardee;
use App\Services\Monitoring\MonitoringEvaluationCache;
use App\Services\Names\UkrainianNameNormalizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('awardees:normalize-names {--apply : Write the corrected names to the database}')]
#[Description('Recalculate awardee names from their stored source name parts')]
final class NormalizeAwardeeNames extends Command
{
    public function handle(UkrainianNameNormalizer $normalizer, MonitoringEvaluationCache $evaluationCache): int
    {
        $apply = (bool) $this->option('apply');
        $updated = 0;
        $missingSource = 0;
        $conflicts = 0;

        Awardee::query()->chunkById(100, function (Collection $awardees) use ($apply, $normalizer, &$conflicts, &$missingSource, &$updated): void {
            foreach ($awardees as $awardee) {
                $this->processAwardee($awardee, $apply, $normalizer, $updated, $missingSource, $conflicts);
            }
        });

        if ($apply && $updated > 0) {
            $evaluationCache->invalidate();
        }

        $this->newLine();
        $this->info(($apply ? 'Updated' : 'Would update').": {$updated}; skipped (missing source name parts): {$missingSource}; skipped (name conflicts): {$conflicts}.");

        if (! $apply) {
            $this->comment('Dry run only. Review the proposed changes, then re-run with --apply to write them.');
        }

        return self::SUCCESS;
    }

    private function processAwardee(
        Awardee $awardee,
        bool $apply,
        UkrainianNameNormalizer $normalizer,
        int &$updated,
        int &$missingSource,
        int &$conflicts,
    ): void {
        if ($awardee->surname === null || trim($awardee->surname) === ''
            || $awardee->given_name === null || trim($awardee->given_name) === '') {
            $missingSource++;

            return;
        }

        $nameParts = array_filter(
            [$awardee->surname, $awardee->given_name, $awardee->patronymic],
            static fn (?string $part): bool => $part !== null && trim($part) !== '',
        );
        $normalizedName = $normalizer->toNominative(implode(' ', $nameParts));

        if ($normalizedName === $awardee->full_name) {
            return;
        }

        if ($this->hasNameConflict($awardee, $normalizedName)) {
            $conflicts++;
            $this->warn("Skipped awardee {$awardee->id}: '{$normalizedName}' conflicts with another awardee.");

            return;
        }

        if ($apply) {
            $awardee->update(['full_name' => $normalizedName]);
            $updated++;
            $this->line("Updated awardee {$awardee->id}: '{$normalizedName}'.");
        } else {
            $updated++;
            $this->line("Would update awardee {$awardee->id}: '{$awardee->full_name}' => '{$normalizedName}'.");
        }
    }

    private function hasNameConflict(Awardee $awardee, string $normalizedName): bool
    {
        $query = Awardee::query()
            ->where('order_id', $awardee->order_id)
            ->where('full_name', $normalizedName)
            ->where('id', '!=', $awardee->id);

        if ($awardee->award === null) {
            $query->whereNull('award');
        } else {
            $query->where('award', $awardee->award);
        }

        return $query->exists();
    }
}
