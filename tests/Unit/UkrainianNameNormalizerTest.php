<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Names\UkrainianNameNormalizer;
use PHPUnit\Framework\TestCase;

final class UkrainianNameNormalizerTest extends TestCase
{
    public function test_common_names_are_converted_to_nominative(): void
    {
        $normalizer = new UkrainianNameNormalizer;

        $this->assertSame('Олександр Вікторович', $normalizer->toNominative('Олександра Вікторовича'));
        $this->assertSame('Михайло Володимирович', $normalizer->toNominative('Михайла Володимировича'));
        $this->assertSame('Ірина Вікторівна', $normalizer->toNominative('Ірину Вікторівну'));
        $this->assertSame('БАКАЛ', $normalizer->toNominative('БАКАЛА'));
        $this->assertSame('ЄМЕЦЬ', $normalizer->toNominative('ЄМЦЙ'));
    }
}
