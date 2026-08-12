<?php

declare(strict_types=1);

use Aybarsm\Laravel\WhoisJson\Enums\Endpoint;
use Aybarsm\Laravel\WhoisJson\Facades\WhoisJson as WhoisJsonFacade;
use Aybarsm\Laravel\WhoisJson\WhoisJson;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('registers the service as a singleton behind both bindings', function (): void {
    expect(app(WhoisJson::class))
        ->toBe(app(WhoisJson::class))
        ->toBe(app('whoisjson'));
});

it('merges the package config', function (): void {
    expect(config('whoisjson.api_base_url'))->toBe('https://whoisjson.com/api/v1')
        ->and(config('whoisjson.api_token_type'))->toBe('TOKEN=');
});

it('publishes the config file', function (): void {
    $paths = \Illuminate\Support\ServiceProvider::pathsToPublish(
        \Aybarsm\Laravel\WhoisJson\WhoisJsonServiceProvider::class,
        'whoisjson-config'
    );

    expect($paths)->toHaveCount(1)
        ->and(array_values($paths)[0])->toEndWith('whoisjson.php');
});

it('resolves through the facade', function (): void {
    Http::preventStrayRequests();
    Http::fake(['whoisjson.com/*' => Http::response(['domain' => 'example.com', 'available' => true])]);

    expect(WhoisJsonFacade::isAvailable('example.com'))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'domain-availability'));
});

it('maps every documented endpoint to its path', function (): void {
    expect(array_map(fn (Endpoint $case): string => $case->path(), Endpoint::cases()))
        ->toBe([
            'whois',
            'reverseWhois',
            'nslookup',
            'ssl-cert-check',
            'domain-availability',
            'subdomains',
            'domain/email-intelligence',
        ]);
});
