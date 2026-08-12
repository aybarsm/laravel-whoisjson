<?php

declare(strict_types=1);

namespace Aybarsm\Laravel\WhoisJson;

use Illuminate\Support\ServiceProvider;

class WhoisJsonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConfig();
        $this->app->singleton(WhoisJson::class, WhoisJson::class);
        $this->app->alias(WhoisJson::class, 'whoisjson');
    }

    public function boot(): void
    {
        $this->publishConfig();
    }

    private function registerConfig(): void
    {
        $this->mergeConfigFrom(
            path: __DIR__.'/../config/whoisjson.php',
            key: 'whoisjson'
        );
    }

    private function publishConfig(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes(
            paths: [__DIR__.'/../config/whoisjson.php' => $this->app->configPath('whoisjson.php')],
            groups: ['whoisjson', 'whoisjson-config']
        );
    }
}
