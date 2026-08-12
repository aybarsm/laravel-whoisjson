<?php

declare(strict_types=1);

use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    $this->freezeTime();

    // Faked sleeps advance Carbon, so the rolling window really does roll.
    Sleep::fake(syncWithCarbon: true);
});

afterEach(function (): void {
    Sleep::fake(false);
});

it('does not pace requests when the rate limit is disabled', function (): void {
    fakeWhoisJson();

    $whoisjson = whoisjson(['api_rate_limit' => 0]);

    expect($whoisjson->limiter()->enabled())->toBeFalse();

    foreach (range(1, 5) as $ignored) {
        $whoisjson->whois('example.com');
    }

    Sleep::assertNeverSlept();
    Http::assertSentCount(5);
});

it('lets a full window through without waiting', function (): void {
    fakeWhoisJson();

    $whoisjson = whoisjson(['api_rate_limit' => 3]);

    foreach (range(1, 3) as $ignored) {
        $whoisjson->whois('example.com');
    }

    Sleep::assertNeverSlept();

    expect($whoisjson->limiter()->used())->toBe(3)
        ->and($whoisjson->limiter()->remaining())->toBe(0);
});

it('waits for the window to roll before exceeding the limit', function (): void {
    fakeWhoisJson();

    $whoisjson = whoisjson(['api_rate_limit' => 2]);

    $whoisjson->whois('one.com');
    $this->travel(10)->seconds();
    $whoisjson->whois('two.com');

    // The third call has to wait out the remaining 50s of the first call's slot.
    $whoisjson->whois('three.com');

    Sleep::assertSlept(fn (CarbonInterval $duration): bool => $duration->totalMilliseconds === 50_000.0, times: 1);

    Http::assertSentCount(3);
});

it('forgets hits that have aged out of the window', function (): void {
    fakeWhoisJson();

    $whoisjson = whoisjson(['api_rate_limit' => 1]);

    $whoisjson->whois('one.com');

    expect($whoisjson->limiter()->remaining())->toBe(0);

    $this->travel(61)->seconds();

    expect($whoisjson->limiter()->used())->toBe(0);

    $whoisjson->whois('two.com');

    Sleep::assertNeverSlept();
});

it('paces the raw pending request escape hatch too', function (): void {
    fakeWhoisJson();

    $whoisjson = whoisjson(['api_rate_limit' => 1]);

    $whoisjson->request()->get('whois', ['domain' => 'one.com']);
    $whoisjson->request()->get('whois', ['domain' => 'two.com']);

    Sleep::assertSlept(fn (CarbonInterval $duration): bool => $duration->totalMilliseconds === 60_000.0, times: 1);
});

it('counts every retry attempt against the rate limit', function (): void {
    Http::preventStrayRequests();
    Http::fake(['whoisjson.com/*' => Http::sequence()
        ->push(['error' => 'Internal error'], 500)
        ->push(['ok' => true], 200),
    ]);

    $whoisjson = whoisjson(['api_rate_limit' => 5, 'retry' => ['times' => 2, 'sleep' => 0]]);

    $whoisjson->whois('example.com');

    Http::assertSentCount(2);
    expect($whoisjson->limiter()->used())->toBe(2);
});

it('shares the window across clones made by fresh()', function (): void {
    fakeWhoisJson();

    $whoisjson = whoisjson(['api_rate_limit' => 10]);

    $whoisjson->fresh()->whois('one.com');
    $whoisjson->whois('two.com');

    expect($whoisjson->limiter()->used())->toBe(2);
});

it('reports an unbounded allowance when disabled', function (): void {
    expect(whoisjson(['api_rate_limit' => 0])->limiter()->remaining())->toBe(PHP_INT_MAX);
});
