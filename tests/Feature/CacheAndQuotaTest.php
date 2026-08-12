<?php

declare(strict_types=1);

use Aybarsm\Laravel\WhoisJson\Exceptions\WhoisJsonException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('does not bypass the upstream cache by default', function (): void {
    fakeWhoisJson();

    whoisjson()->whois('example.com');

    Http::assertSent(fn (Request $request): bool => ! str_contains($request->url(), '_forceRefresh'));
});

it('bypasses the upstream cache per call via fresh()', function (): void {
    fakeWhoisJson();

    whoisjson()->fresh()->whois('example.com');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '_forceRefresh=1'));
});

it('leaves the original instance untouched when cloning', function (): void {
    fakeWhoisJson();

    $whoisjson = whoisjson();
    $whoisjson->fresh()->whois('one.com');
    $whoisjson->whois('two.com');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'one.com')
        && str_contains($request->url(), '_forceRefresh=1')
    );

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'two.com')
        && ! str_contains($request->url(), '_forceRefresh')
    );
});

it('bypasses the upstream cache globally when configured', function (): void {
    fakeWhoisJson();

    whoisjson(['force_refresh' => true])->whois('example.com');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '_forceRefresh=1'));
});

it('opts back into the upstream cache via cached()', function (): void {
    fakeWhoisJson();

    whoisjson(['force_refresh' => true])->cached()->whois('example.com');

    Http::assertSent(fn (Request $request): bool => ! str_contains($request->url(), '_forceRefresh'));
});

it('reports the remaining quota from the response header', function (): void {
    fakeWhoisJson(headers: ['Remaining-Requests' => '452']);

    $whoisjson = whoisjson();

    expect($whoisjson->remainingRequests())->toBeNull();

    $whoisjson->whois('example.com');

    expect($whoisjson->remainingRequests())->toBe(452)
        ->and($whoisjson->lastResponse()->json())->toBe(['ok' => true]);
});

it('reports the remaining quota from a failed call too', function (): void {
    fakeWhoisJson(['error' => 'Limit exceeded'], 429, ['Remaining-Requests' => '0']);

    expect(fn () => whoisjson(['retry' => ['times' => 1, 'sleep' => 0]])->whois('example.com'))
        ->toThrow(fn (WhoisJsonException $e) => expect($e->remainingRequests())->toBe(0));
});
