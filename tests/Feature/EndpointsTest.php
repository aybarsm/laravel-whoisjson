<?php

declare(strict_types=1);

use Aybarsm\Laravel\WhoisJson\Enums\Endpoint;
use Aybarsm\Laravel\WhoisJson\Exceptions\WhoisJsonException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('sends every documented endpoint to the right url with the right query', function (
    string $method,
    array $arguments,
    Endpoint $endpoint,
    array $query,
): void {
    fakeWhoisJson();

    whoisjson()->{$method}(...$arguments);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://whoisjson.com/api/v1/'.$endpoint->value.'?'.http_build_query($query)
        && $request->method() === 'GET'
    );
})->with([
    'whois' => ['whois', ['example.com'], Endpoint::Whois, ['domain' => 'example.com']],
    'reverse whois' => ['reverseWhois', ['8.8.8.8'], Endpoint::ReverseWhois, ['ip' => '8.8.8.8']],
    'nslookup' => ['nsLookup', ['example.com'], Endpoint::NsLookup, ['domain' => 'example.com']],
    'ssl cert check' => ['sslCertCheck', ['example.com'], Endpoint::SslCertCheck, ['domain' => 'example.com']],
    'domain availability' => ['domainAvailability', ['example.com'], Endpoint::DomainAvailability, ['domain' => 'example.com']],
    'subdomains' => ['subdomains', ['discord.com'], Endpoint::Subdomains, ['domain' => 'discord.com']],
    'email intelligence' => ['emailIntelligence', ['user@example.com'], Endpoint::EmailIntelligence, ['email' => 'user@example.com']],
    'domain intelligence' => ['domainIntelligence', ['example.com'], Endpoint::EmailIntelligence, ['domain' => 'example.com']],
]);

it('authenticates with the TOKEN= authorization header', function (): void {
    fakeWhoisJson();

    whoisjson()->whois('example.com');

    Http::assertSent(fn (Request $request): bool => $request->header('Authorization') === ['TOKEN=test-api-key']
        && $request->header('Accept') === ['application/json']
    );
});

it('respects a custom token type without mangling its separator', function (): void {
    fakeWhoisJson();

    whoisjson(['api_token_type' => 'Bearer '])->whois('example.com');

    Http::assertSent(fn (Request $request): bool => $request->header('Authorization') === ['Bearer test-api-key']);
});

it('honours a custom base url', function (): void {
    fakeWhoisJson();

    whoisjson(['api_base_url' => 'https://whoisjson.com/api/v2'])->whois('example.com');

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://whoisjson.com/api/v2/whois'));
});

it('returns the decoded json payload', function (): void {
    fakeWhoisJson(['domain' => 'example.com', 'available' => true]);

    expect(whoisjson()->domainAvailability('example.com'))
        ->toBe(['domain' => 'example.com', 'available' => true]);
});

it('reduces the availability check to a boolean', function (): void {
    fakeWhoisJson(['domain' => 'example.com', 'available' => false]);

    expect(whoisjson()->isAvailable('example.com'))->toBeFalse();
});

it('accepts either an email or a domain for intelligence scoring', function (): void {
    fakeWhoisJson();

    whoisjson()->intelligence(domain: 'example.com');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://whoisjson.com/api/v1/domain/email-intelligence?domain=example.com');
});

it('rejects an intelligence call with neither an email nor a domain', function (): void {
    whoisjson()->intelligence();
})->throws(WhoisJsonException::class, 'requires either an email address or a domain name');

it('requests xml when asked to', function (): void {
    fakeWhoisJson('<whois><name>example.com</name></whois>');

    $xml = whoisjson()->xml(Endpoint::Whois, ['domain' => 'example.com']);

    expect($xml)->toBe('<whois><name>example.com</name></whois>');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'format=xml')
        && $request->header('Accept') === ['application/xml']
    );
});

it('reaches undocumented paths through the generic getter', function (): void {
    fakeWhoisJson();

    whoisjson()->get('/some/new-endpoint', ['domain' => 'example.com']);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://whoisjson.com/api/v1/some/new-endpoint?domain=example.com');
});

it('exposes a preconfigured pending request as an escape hatch', function (): void {
    fakeWhoisJson();

    whoisjson()->request()->get('whois', ['domain' => 'example.com']);

    Http::assertSent(fn (Request $request): bool => $request->header('Authorization') === ['TOKEN=test-api-key']);
});
