<?php

use App\Models\Awardee;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('previews normalized names without changing the database', function (): void {
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

    expect(Artisan::call('awardees:normalize-names'))->toBe(0);

    expect($awardee->refresh()->full_name)->toBe('БАКАЛА Олександра Вікторовича');
});

it('applies normalized names and skips records without source name parts', function (): void {
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
    $manualAwardee = Awardee::query()->create([
        'order_id' => $order->id,
        'full_name' => 'Ручне ім’я',
        'award' => 'орден',
    ]);

    expect(Artisan::call('awardees:normalize-names', ['--apply' => true]))->toBe(0);

    expect($awardee->refresh()->full_name)->toBe('БАКАЛ Олександр Вікторович');
    expect($manualAwardee->refresh()->full_name)->toBe('Ручне ім’я');
});

it('skips a recalculated name that conflicts with another awardee', function (): void {
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
    Awardee::query()->create([
        'order_id' => $order->id,
        'full_name' => 'БАКАЛ Олександр Вікторович',
        'award' => 'медаль',
    ]);

    expect(Artisan::call('awardees:normalize-names', ['--apply' => true]))->toBe(0);

    expect($awardee->refresh()->full_name)->toBe('БАКАЛА Олександра Вікторовича');
});
