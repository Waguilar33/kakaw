<?php

namespace Kakaw;

use Illuminate\Support\ServiceProvider;

class KakawServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/kakaw.php', 'kakaw');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/kakaw.php' => config_path('kakaw.php'),
        ], 'kakaw-config');
    }
}
