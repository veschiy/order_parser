<?php

use App\Jobs\NormalizeAwardeeNamesForAppVersion;
use App\Providers\NativeAppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Native\Desktop\Client\Client;
use Native\Desktop\Facades\Window;
use Native\Desktop\Fakes\WindowManagerFake;
use Native\Desktop\Windows\Window as DesktopWindow;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('opens the desktop window before scheduling name normalization', function (): void {
    config(['nativephp.version' => '2.0.0']);
    Cache::store('database')->forget('awardees.normalized_names_version');

    Http::fake();
    $queue = Queue::fake();
    $windowManager = new WindowManagerFake(app(Client::class));
    $windowManager->alwaysReturnWindows([new DesktopWindow('main')]);
    Window::swap($windowManager);
    $queue->beforePushing(function () use ($windowManager): void {
        expect($windowManager->opened)->toBe(['main']);
        expect(Http::recorded())->not->toBeEmpty();
    });

    Artisan::shouldReceive('call')
        ->once()
        ->with('migrate', ['--force' => true])
        ->andReturn(0);

    (new NativeAppServiceProvider)->boot();

    $windowManager->assertOpenedCount(1);
    Queue::assertPushed(NormalizeAwardeeNamesForAppVersion::class);
});
