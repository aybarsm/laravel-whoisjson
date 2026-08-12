<?php

declare(strict_types=1);

use Aybarsm\Laravel\WhoisJson\Exceptions\WhoisJsonException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('surfaces the api error message and status', function (int $status, string $error): void {
    Http::fake(['whoisjson.com/*' => Http::response(['error' => $error], $status)]);

    expect(fn () => whoisjson()->whois('example.com'))
        ->toThrow(function (WhoisJsonException $e) use ($status, $error): void {
            expect($e->status)->toBe($status)
                ->and($e->getMessage())->toBe("whoisjson.com [{$status}]: {$error}")
                ->and($e->response)->not->toBeNull();
        });
})->with([
    [400, 'Invalid domain name.'],
    [401, 'Invalid API key.'],
    [403, 'Email address not validated.'],
]);

it('falls back to a documented message when the body carries none', function (): void {
    Http::fake(['whoisjson.com/*' => Http::response(null, 401)]);

    expect(fn () => whoisjson()->whois('example.com'))
        ->toThrow(WhoisJsonException::class, 'the API key is missing or invalid');
});

it('flags rate limiting', function (): void {
    Http::fake(['whoisjson.com/*' => Http::response(['error' => 'Limit exceeded'], 429)]);

    expect(fn () => whoisjson(['retry' => ['times' => 1, 'sleep' => 0]])->whois('example.com'))
        ->toThrow(fn (WhoisJsonException $e) => expect($e->rateLimited())->toBeTrue());
});

it('treats an error key in a 200 body as a failure', function (): void {
    Http::fake(['whoisjson.com/*' => Http::response(['error' => 'Invalid domain name.'])]);

    expect(fn () => whoisjson()->whois('not a domain'))
        ->toThrow(WhoisJsonException::class, 'whoisjson.com [200]: Invalid domain name.');
});

it('refuses to send a request without an api key', function (): void {
    expect(fn () => whoisjson(['api_key' => null])->whois('example.com'))
        ->toThrow(WhoisJsonException::class, 'No whoisjson.com API key configured');

    Http::assertNothingSent();
});

it('retries retryable statuses and returns the eventual success', function (): void {
    Http::fake(['whoisjson.com/*' => Http::sequence()
        ->push(['error' => 'Internal error'], 500)
        ->push(['domain' => 'example.com', 'available' => true], 200),
    ]);

    expect(whoisjson()->domainAvailability('example.com'))
        ->toBe(['domain' => 'example.com', 'available' => true]);

    Http::assertSentCount(2);
});

it('does not retry client errors it cannot recover from', function (): void {
    Http::fake(['whoisjson.com/*' => Http::response(['error' => 'Invalid domain name.'], 400)]);

    expect(fn () => whoisjson()->whois('example.com'))->toThrow(WhoisJsonException::class);

    Http::assertSentCount(1);
});

it('gives up after the configured number of attempts', function (): void {
    Http::fake(['whoisjson.com/*' => Http::response(['error' => 'Internal error'], 500)]);

    expect(fn () => whoisjson(['retry' => ['times' => 3, 'sleep' => 0]])->whois('example.com'))
        ->toThrow(WhoisJsonException::class, 'whoisjson.com [500]: Internal error');

    Http::assertSentCount(3);
});
