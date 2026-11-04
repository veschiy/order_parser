<?php

declare(strict_types=1);

namespace App\Providers;

use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        Window::open()
            ->title('Order Parser 1.0')
            ->width(1600)
            ->height(1000)
            ->minWidth(960)
            ->minHeight(640)
            ->maximized()
            ->hideMenu();
    }

    /**
     * Return an array of php.ini directives to be set.
     *
     * @return array<string, mixed>
     */
    public function phpIni(): array
    {
        return [
        ];
    }
}
