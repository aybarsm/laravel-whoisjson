<?php

declare(strict_types=1);

use Aybarsm\Laravel\WhoisJson\WhoisJson;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()->extend(TestCase::class)->in(__DIR__);

/**
 * Stub every whoisjson.com call and forbid requests to anywhere else.
 *
 * `Http::fake()` merges stubs rather than replacing them, so this belongs in
 * the test body — not in a `beforeEach` a test might want to override.
 *
 * @param  array<string, string>  $headers
 */
function fakeWhoisJson(mixed $body = ['ok' => true], int $status = 200, array $headers = []): void
{
    Http::preventStrayRequests();
    Http::fake(['whoisjson.com/*' => Http::response($body, $status, $headers)]);
}

/**
 * Resolve a fresh service instance, optionally overriding config first.
 *
 * The service is a singleton, so the container instance has to be discarded
 * whenever a test changes the configuration it was built from.
 *
 * @param  array<string, mixed>  $config
 */
function whoisjson(array $config = []): WhoisJson
{
    foreach ($config as $key => $value) {
        config()->set("whoisjson.{$key}", $value);
    }

    app()->forgetInstance(WhoisJson::class);

    return app(WhoisJson::class);
}
