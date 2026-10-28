<?php

namespace App\Providers;

use App\Models\PlatformCooperative;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('platform.layout', function ($view): void {
            $view->with(
                'platformCooperatives',
                PlatformCooperative::query()->orderBy('name')->get(['id', 'name', 'status']),
            );
        });
    }
}
