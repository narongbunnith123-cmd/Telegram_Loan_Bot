<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind Console Kernel so schedule:work works
        $this->app->singleton(
            \Illuminate\Contracts\Console\Kernel::class,
            \App\Console\Kernel::class,
        );
    }

    public function boot(): void
    {
        //
    }
}