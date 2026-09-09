<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

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
        // API JSON shapes in SPEC.md use bare arrays / top-level objects,
        // so disable the default "data" wrapping for single resources and collections.
        JsonResource::withoutWrapping();
    }
}
