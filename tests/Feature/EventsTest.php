<?php

declare(strict_types=1);

use Aybarsm\Laravel\WhoisJson\Enums\Endpoint;
use Aybarsm\Laravel\WhoisJson\Events\ResponseVerified;
use Aybarsm\Laravel\WhoisJson\Exceptions\WhoisJsonException;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    Event::fake([ResponseVerified::class]);
});

it('dispatches a verified response event carrying the call it describes', function (): void {
    fakeWhoisJson(['domain' => 'example.com', 'available' => true], headers: ['Remaining-Requests' => '452']);

    whoisjson()->domainAvailability('example.com');

    Event::assertDispatched(ResponseVerified::class, function (ResponseVerified $event): bool {
        expect($event->endpoint)->toBe(Endpoint::DomainAvailability)
            ->and($event->query)->toBe(['domain' => 'example.com'])
            ->and($event->json())->toBe(['domain' => 'example.com', 'available' => true])
            ->and($event->remainingRequests())->toBe(452)
            ->and($event->forcedRefresh())->toBeFalse()
            ->and($event->response->successful())->toBeTrue();

        return true;
    });
});

it('dispatches exactly once per verified call', function (): void {
    fakeWhoisJson();

    whoisjson()->whois('example.com');

    Event::assertDispatchedTimes(ResponseVerified::class, 1);
});

it('does not dispatch when the api reports a failure', function (int $status, array $body): void {
    fakeWhoisJson($body, $status);

    expect(fn () => whoisjson(['retry' => ['times' => 1, 'sleep' => 0]])->whois('example.com'))
        ->toThrow(WhoisJsonException::class);

    Event::assertNotDispatched(ResponseVerified::class);
})->with([
    'client error' => [400, ['error' => 'Invalid domain name.']],
    'unauthorized' => [401, ['error' => 'Invalid API key.']],
    'rate limited' => [429, ['error' => 'Limit exceeded']],
    'error key on a 200' => [200, ['error' => 'Invalid domain name.']],
]);

it('does not dispatch when there is no api key to send with', function (): void {
    expect(fn () => whoisjson(['api_key' => null])->whois('example.com'))
        ->toThrow(WhoisJsonException::class);

    Event::assertNotDispatched(ResponseVerified::class);
});

it('dispatches only for the attempt that succeeded', function (): void {
    Illuminate\Support\Facades\Http::preventStrayRequests();
    Illuminate\Support\Facades\Http::fake(['whoisjson.com/*' => Illuminate\Support\Facades\Http::sequence()
        ->push(['error' => 'Internal error'], 500)
        ->push(['ok' => true], 200),
    ]);

    whoisjson()->whois('example.com');

    Event::assertDispatchedTimes(ResponseVerified::class, 1);
});

it('reports the forced cache bypass on the event', function (): void {
    fakeWhoisJson();

    whoisjson()->fresh()->whois('example.com');

    Event::assertDispatched(ResponseVerified::class, function (ResponseVerified $event): bool {
        expect($event->forcedRefresh())->toBeTrue()
            ->and($event->query)->toBe(['domain' => 'example.com', '_forceRefresh' => 1]);

        return true;
    });
});

it('reports the endpoint verbatim for generic paths', function (): void {
    fakeWhoisJson();

    whoisjson()->get('/some/new-endpoint', ['domain' => 'example.com']);

    Event::assertDispatched(ResponseVerified::class, function (ResponseVerified $event): bool {
        expect($event->endpoint)->toBe('/some/new-endpoint');

        return true;
    });
});

it('dispatches for xml responses too', function (): void {
    fakeWhoisJson('<whois><name>example.com</name></whois>');

    whoisjson()->xml(Endpoint::Whois, ['domain' => 'example.com']);

    Event::assertDispatched(ResponseVerified::class, function (ResponseVerified $event): bool {
        expect($event->query)->toBe(['domain' => 'example.com', 'format' => 'xml'])
            ->and($event->json())->toBe([]);

        return true;
    });
});

it('does not dispatch for the raw pending request escape hatch', function (): void {
    fakeWhoisJson();

    whoisjson()->request()->get('whois', ['domain' => 'example.com']);

    Event::assertNotDispatched(ResponseVerified::class);
});
