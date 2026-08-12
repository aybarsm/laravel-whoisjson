# Laravel WhoisJSON

An opinionated Laravel wrapper for the [whoisjson.com](https://whoisjson.com/documentation#endpoints) API — WHOIS, DNS, SSL, domain availability, subdomain discovery, email domain intelligence and reverse WHOIS behind one service, one facade and one API key.

## Requirements

- PHP `^8.4`
- Laravel `^13.0`

## Installation

```bash
composer require aybarsm/laravel-whoisjson
```

The service provider and the `WhoisJson` facade alias are auto-discovered. Publish the config if you want to tune it in the repo:

```bash
php artisan vendor:publish --tag=whoisjson-config
```

## Configuration

Grab a key from [whoisjson.com](https://whoisjson.com) (the free plan includes 1,000 requests/month) and add it to your `.env`:

```dotenv
WHOISJSON_API_KEY=your-api-key
```

Everything else has a working default:

| Variable                     | Default                          | Notes                                                        |
|------------------------------|----------------------------------|--------------------------------------------------------------|
| `WHOISJSON_API_KEY`          | —                                | Required.                                                     |
| `WHOISJSON_API_BASE_URL`     | `https://whoisjson.com/api/v1`   |                                                               |
| `WHOISJSON_API_TOKEN_TYPE`   | `TOKEN=`                         | Prefix for the `Authorization` header.                        |
| `WHOISJSON_API_RATE_LIMIT`   | `0`                              | Requests per minute. `0` disables client-side pacing.         |
| `WHOISJSON_TIMEOUT`          | `30`                             | Seconds.                                                      |
| `WHOISJSON_CONNECT_TIMEOUT`  | `10`                             | Seconds.                                                      |
| `WHOISJSON_RETRY_TIMES`      | `2`                              | Total attempts. `1` disables retrying.                        |
| `WHOISJSON_RETRY_SLEEP`      | `1000`                           | Milliseconds between attempts.                                |
| `WHOISJSON_FORCE_REFRESH`    | `false`                          | Sends `_forceRefresh=1` on every call. Costs 2x credits.      |

Retries cover connection failures plus `429`, `500`, `502`, `503` and `504`. Other 4xx statuses fail immediately.

### Rate limiting

The API enforces a per-minute limit on a rolling 60-second window, and the ceiling depends on your plan:

| Plan  | Monthly quota   | `WHOISJSON_API_RATE_LIMIT` |
|-------|-----------------|-----------------------------|
| Basic | 1,000 req/mo    | `20`                        |
| Pro   | 30,000 req/mo   | `40`                        |
| Ultra | 150,000 req/mo  | `60`                        |
| Scale | 1,000,000 req/mo| `100`                       |
| Mega  | unlimited       | `100`                       |
| Giga  | unlimited       | `200`                       |
| Tera  | unlimited       | `300`                       |
| Atlas | unlimited       | `900`                       |

Set yours and the package paces itself over the same rolling window — a call that would exceed the limit waits for the next free slot instead of earning a `429`:

```dotenv
WHOISJSON_API_RATE_LIMIT=40
```

The default is `0`, which disables pacing entirely: without knowing your plan the package cannot pick a safe number, and guessing the Basic tier would silently make a Scale-plan bulk run five times slower. Leaving it off is not unsafe — `429`s are still retried — but setting it is strongly recommended for bulk work.

Window state lives in your **default cache store**, so the limit is enforced across every process sharing that store (Redis, database, …). With the `array` store it is per-process. Retry attempts each count as a call, as they do server-side, and the throttle applies to `request()` too.

```php
WhoisJson::limiter()->enabled();     // bool
WhoisJson::limiter()->used();        // calls made in the current window
WhoisJson::limiter()->remaining();   // calls still allowed in it
```

## Usage

Resolve the service however you prefer — facade, container, or constructor injection:

```php
use Aybarsm\Laravel\WhoisJson\Facades\WhoisJson;

WhoisJson::whois('example.com');
```

```php
use Aybarsm\Laravel\WhoisJson\WhoisJson;

public function __construct(private WhoisJson $whoisjson) {}
```

### The seven endpoints

```php
// GET /whois — registrar, dates, nameservers, status codes, contacts
WhoisJson::whois('example.com');

// GET /nslookup — A, AAAA, CAA, CNAME, MX, NS, SOA, TXT
WhoisJson::nsLookup('example.com');

// GET /ssl-cert-check — issuer, validity window, chain details
WhoisJson::sslCertCheck('example.com');

// GET /domain-availability
WhoisJson::domainAvailability('example.com');   // ['domain' => ..., 'available' => bool]
WhoisJson::isAvailable('example.com');          // bool

// GET /subdomains — active subdomains with A & CNAME records
WhoisJson::subdomains('discord.com');

// GET /domain/email-intelligence — risk score for the domain behind a signup
WhoisJson::emailIntelligence('user@example.com');
WhoisJson::domainIntelligence('example.com');
WhoisJson::intelligence(email: 'user@example.com', domain: null);

// GET /reverseWhois — domains associated with an IP address
WhoisJson::reverseWhois('8.8.8.8');
```

Every method returns the decoded JSON body as an array.

### Cache bypass

Responses are cached upstream for three hours. `fresh()` returns a copy of the service that bypasses that cache for the calls made through it — at 2x credits:

```php
WhoisJson::fresh()->whois('example.com');   // sends _forceRefresh=1
```

If `WHOISJSON_FORCE_REFRESH` is on globally, `cached()` opts an individual call back in:

```php
WhoisJson::cached()->whois('example.com');
```

Both return a clone; the underlying singleton is never mutated.

### Quota tracking

The API reports the calls left in your billing period on every response:

```php
WhoisJson::whois('example.com');
WhoisJson::remainingRequests();   // int|null, from the Remaining-Requests header
WhoisJson::lastResponse();        // Illuminate\Http\Client\Response|null
```

Both read the state of the instance you call them on, so a call made through `fresh()` or `cached()` records onto that clone — keep a reference to it if you need its quota reading.

### Error handling

Any non-2xx status — and any `200` whose body carries an `error` key — throws a `WhoisJsonException`:

```php
use Aybarsm\Laravel\WhoisJson\Exceptions\WhoisJsonException;

try {
    $whois = WhoisJson::whois('example.com');
} catch (WhoisJsonException $e) {
    $e->getMessage();          // "whoisjson.com [401]: Invalid API key."
    $e->status;                // 401
    $e->response;              // Illuminate\Http\Client\Response|null
    $e->rateLimited();         // true on 429
    $e->remainingRequests();   // int|null
}
```

### XML and undocumented paths

```php
use Aybarsm\Laravel\WhoisJson\Enums\Endpoint;

// Raw XML body
WhoisJson::xml(Endpoint::Whois, ['domain' => 'example.com']);

// Any path under the base url, verified and decoded like the rest
WhoisJson::get('/some/new-endpoint', ['domain' => 'example.com']);

// The verified Response object
WhoisJson::response(Endpoint::Subdomains, ['domain' => 'example.com'])->json('total_found');

// A fully configured PendingRequest — no verification, no decoding
WhoisJson::request()->get('whois', ['domain' => 'example.com']);
```

## Testing

The client is built from Laravel's `Illuminate\Http\Client\Factory`, so `Http::fake()` works in your application's tests without any extra scaffolding:

```php
Http::fake(['whoisjson.com/*' => Http::response(['domain' => 'example.com', 'available' => true])]);

expect(WhoisJson::isAvailable('example.com'))->toBeTrue();
```

The package's own suite:

```bash
composer test
```

## License

MIT.
