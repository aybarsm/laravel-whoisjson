<?php

declare(strict_types=1);

namespace Tests;

use Aybarsm\Laravel\WhoisJson\Facades\WhoisJson;
use Aybarsm\Laravel\WhoisJson\WhoisJsonServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [WhoisJsonServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['WhoisJson' => WhoisJson::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('whoisjson.api_key', 'test-api-key');
        $app['config']->set('whoisjson.retry.sleep', 0);
    }
}
