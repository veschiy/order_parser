<?php

use App\Models\Awardee;
use App\Models\Order;
use App\Services\Names\NormalizeAwardeeNamesOnAppVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    config(['nativephp.version' => '2.0.0']);

    Cache::store('database')->forget('awardees.normalized_names_version');
});

it('normalizes names once for each app version', function (): void {
    $order = Order::query()->create([
        'document_url' => 'https://example.com/decree',
        'parsed_at' => now(),
    ]);

    $awardee = Awardee::query()->create([
        'order_id' => $order->id,
        'full_name' => 'БАКАЛА Олександра Вікторовича',
        'surname' => 'БАКАЛА',
        'given_name' => 'Олександра',
        'patronymic' => 'Вікторовича',
        'award' => 'медаль',
    ]);
    $normalizer = new NormalizeAwardeeNamesOnAppVersion;

    $normalizer->runIfAppVersionChanged();

    expect($awardee->refresh()->full_name)->toBe('БАКАЛ Олександр Вікторович');

    $awardee->update(['full_name' => 'БАКАЛА Олександра Вікторовича']);
    $normalizer->runIfAppVersionChanged();

    expect($awardee->refresh()->full_name)->toBe('БАКАЛА Олександра Вікторовича');

    config(['nativephp.version' => '2.0.1']);
    $normalizer->runIfAppVersionChanged();

    expect($awardee->refresh()->full_name)->toBe('БАКАЛ Олександр Вікторович');
});

it('does not record the version when normalization fails', function (): void {
    Artisan::shouldReceive('call')
        ->twice()
        ->with('awardees:normalize-names', ['--apply' => true])
        ->andReturn(1);

    $normalizer = new NormalizeAwardeeNamesOnAppVersion;

    $normalizer->runIfAppVersionChanged();
    $normalizer->runIfAppVersionChanged();

    expect(Cache::store('database')->get('awardees.normalized_names_version'))->toBeNull();
});
