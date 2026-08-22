<?php

declare(strict_types=1);

use Aybarsm\Laravel\WhoisJson\Exceptions\WhoisJsonException;
use Aybarsm\Laravel\WhoisJson\WhoisJson;
use Illuminate\Support\Facades\Cache;

it('caches the quota reading on every successful request', function (): void {
    fakeWhoisJson(headers: ['Remaining-Requests' => '452']);

    $whoisjson = whoisjson();

    expect($whoisjson->cachedRemainingRequests())->toBeNull();

    $whoisjson->whois('example.com');

    expect($whoisjson->cachedRemainingRequests())->toBe(452);
});

it('overwrites the cached reading as the quota is spent', function (): void {
    fakeWhoisJsonSequence([
        ['headers' => ['Remaining-Requests' => '452']],
        ['headers' => ['Remaining-Requests' => '451']],
    ]);

    $whoisjson = whoisjson();
    $whoisjson->whois('one.com');
    $whoisjson->whois('two.com');

    expect($whoisjson->cachedRemainingRequests())->toBe(451);
});

it('reads the cached quota when no response has been made yet', function (): void {
    fakeWhoisJson(headers: ['Remaining-Requests' => '452']);

    whoisjson()->whois('example.com');

    // A brand new instance, as a later process would resolve it.
    app()->forgetInstance(WhoisJson::class);
    $whoisjson = app(WhoisJson::class);

    expect($whoisjson->lastResponse())->toBeNull()
        ->and($whoisjson->remainingRequests())->toBe(452);
});

it('prefers the live response over the cached reading', function (): void {
    fakeWhoisJsonSequence([
        ['headers' => ['Remaining-Requests' => '452']],
        ['headers' => ['Remaining-Requests' => '9']],
    ]);

    $whoisjson = whoisjson();
    $whoisjson->whois('one.com');
    $whoisjson->whois('two.com');

    expect($whoisjson->remainingRequests())->toBe(9);
});

it('falls back to the cache when a response carries no quota header', function (): void {
    fakeWhoisJsonSequence([
        ['headers' => ['Remaining-Requests' => '452']],
        [],
    ]);

    $whoisjson = whoisjson();
    $whoisjson->whois('one.com');
    $whoisjson->whois('two.com');

    expect($whoisjson->lastResponse()->header('Remaining-Requests'))->toBe('')
        ->and($whoisjson->remainingRequests())->toBe(452);
});

it('does not cache anything from a failed request', function (): void {
    fakeWhoisJson(['error' => 'Limit exceeded'], 429, ['Remaining-Requests' => '0']);

    expect(fn () => whoisjson(['retry' => ['times' => 1, 'sleep' => 0]])->whois('example.com'))
        ->toThrow(WhoisJsonException::class);

    expect(whoisjson()->cachedRemainingRequests())->toBeNull();
});

it('does not cache when the response omits the quota header', function (): void {
    fakeWhoisJson();

    $whoisjson = whoisjson();
    $whoisjson->whois('example.com');

    expect($whoisjson->cachedRemainingRequests())->toBeNull()
        ->and($whoisjson->remainingRequests())->toBeNull();
});

it('shares the cached reading across every instance using the same credentials', function (): void {
    fakeWhoisJson(headers: ['Remaining-Requests' => '452']);

    whoisjson()->fresh()->whois('example.com');

    expect(whoisjson()->cachedRemainingRequests())->toBe(452);
});

it('keys the cached reading per credential', function (): void {
    fakeWhoisJson(headers: ['Remaining-Requests' => '452']);

    whoisjson(['api_key' => 'first-key'])->whois('example.com');

    expect(whoisjson(['api_key' => 'second-key'])->cachedRemainingRequests())->toBeNull()
        ->and(whoisjson(['api_key' => 'first-key'])->cachedRemainingRequests())->toBe(452);
});

it('lets the cached reading age out', function (): void {
    fakeWhoisJson(headers: ['Remaining-Requests' => '452']);

    $whoisjson = whoisjson();
    $whoisjson->whois('example.com');

    $this->travel(25)->hours();

    expect($whoisjson->cachedRemainingRequests())->toBeNull();
});

it('discards the cached reading on request', function (): void {
    fakeWhoisJson(headers: ['Remaining-Requests' => '452']);

    $whoisjson = whoisjson();
    $whoisjson->whois('example.com');

    expect($whoisjson->forgetRemainingRequests())->toBeTrue()
        ->and($whoisjson->cachedRemainingRequests())->toBeNull();
});

it('ignores a junk value sitting in the cache', function (): void {
    Cache::put('whoisjson:remaining-requests:'.hash('xxh128', 'https://whoisjson.com/api/v1test-api-key'), 'not-a-number', 60);

    expect(whoisjson()->remainingRequests())->toBeNull();
});
