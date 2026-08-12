<?php

declare(strict_types=1);

namespace Aybarsm\Laravel\WhoisJson;

use Illuminate\Support\ServiceProvider;

class WhoisJsonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConfig();
    }

    public function boot(): void
    {

    }

    private function registerConfig(): void
    {
        $this->mergeConfigFrom(
            path: __DIR__ . '/../config/whoisjson.php',
            key: 'whoisjson'
        );
    }
}
