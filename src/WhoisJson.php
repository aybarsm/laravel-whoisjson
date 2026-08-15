<?php

declare(strict_types=1);

namespace Aybarsm\Laravel\WhoisJson;

use Aybarsm\Laravel\WhoisJson\Concerns\ReadsRemainingRequests;
use Aybarsm\Laravel\WhoisJson\Enums\Endpoint;
use Aybarsm\Laravel\WhoisJson\Enums\Format;
use Aybarsm\Laravel\WhoisJson\Events\ResponseVerified;
use Aybarsm\Laravel\WhoisJson\Exceptions\WhoisJsonException;
use Aybarsm\Laravel\WhoisJson\Support\RateLimiter;
use Illuminate\Container\Attributes\Config;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * Wrapper around the whoisjson.com REST API.
 *
 * @see https://whoisjson.com/documentation#endpoints
 */
#[Singleton]
class WhoisJson
{
    use ReadsRemainingRequests;

    /**
     * Statuses worth another attempt: the quota/rate limiter and server-side faults.
     */
    protected const RETRYABLE_STATUSES = [429, 500, 502, 503, 504];

    protected ?Response $lastResponse = null;

    protected ?RateLimiter $limiter = null;

    public function __construct(
        protected readonly Factory $http,
        protected readonly CacheFactory $cache,
        #[Config('whoisjson.api_base_url')] public readonly string $apiBaseUrl,
        #[Config('whoisjson.api_key')] protected readonly ?string $apiKey,
        #[Config('whoisjson.api_token_type')] public readonly string $apiTokenType,
        #[Config('whoisjson.api_rate_limit')] public readonly int $apiRateLimit,
        #[Config('whoisjson.timeout')] public readonly int $timeout,
        #[Config('whoisjson.connect_timeout')] public readonly int $connectTimeout,
        #[Config('whoisjson.retry.times')] public readonly int $retryTimes,
        #[Config('whoisjson.retry.sleep')] public readonly int $retrySleep,
        #[Config('whoisjson.force_refresh')] protected bool $forceRefresh,
    ) {}

    /**
     * Retrieve the full WHOIS record for a domain.
     *
     * @return array{name?: string, status?: array<int, string>, nameserver?: array<int, string>, registrar?: array<string, mixed>, contacts?: array<string, mixed>, registered?: bool, source?: string}
     */
    public function whois(string $domain): array
    {
        return $this->get(Endpoint::Whois, ['domain' => $domain]);
    }

    /**
     * Retrieve WHOIS data for an IPv4 address, including the domains behind it.
     *
     * @return array<string, mixed>
     */
    public function reverseWhois(string $ip): array
    {
        return $this->get(Endpoint::ReverseWhois, ['ip' => $ip]);
    }

    /**
     * Resolve every DNS record (A, AAAA, CAA, CNAME, MX, NS, SOA, TXT) for a domain.
     *
     * @return array<string, mixed>
     */
    public function nsLookup(string $domain): array
    {
        return $this->get(Endpoint::NsLookup, ['domain' => $domain]);
    }

    /**
     * Retrieve SSL/TLS certificate details for a domain.
     *
     * @return array{domain?: string, issuer?: array<string, string>, valid?: bool, valid_from?: string, valid_to?: string, details?: array<string, mixed>}
     */
    public function sslCertCheck(string $domain): array
    {
        return $this->get(Endpoint::SslCertCheck, ['domain' => $domain]);
    }

    /**
     * Check whether a domain name is available for registration.
     *
     * @return array{domain?: string, available?: bool}
     */
    public function domainAvailability(string $domain): array
    {
        return $this->get(Endpoint::DomainAvailability, ['domain' => $domain]);
    }

    /**
     * Answer the availability check as a plain boolean.
     */
    public function isAvailable(string $domain): bool
    {
        return (bool) data_get($this->domainAvailability($domain), 'available', false);
    }

    /**
     * Discover active subdomains (A & CNAME records) for a domain.
     *
     * @return array{domain?: string, wildcard_detected?: bool, total_found?: int, subdomains?: array<int, array<string, mixed>>}
     */
    public function subdomains(string $domain): array
    {
        return $this->get(Endpoint::Subdomains, ['domain' => $domain]);
    }

    /**
     * Score the domain behind a signup email address. The local mailbox is never checked.
     *
     * @return array{domain?: string, domainCategory?: string, riskScore?: int, riskLevel?: string, recommendation?: string, routing?: array<string, string>, signals?: array<int, array<string, mixed>>}
     */
    public function emailIntelligence(string $email): array
    {
        return $this->get(Endpoint::EmailIntelligence, ['email' => $email]);
    }

    /**
     * Score a domain directly, without going through an email address.
     *
     * @return array{domain?: string, domainCategory?: string, riskScore?: int, riskLevel?: string, recommendation?: string, routing?: array<string, string>, signals?: array<int, array<string, mixed>>}
     */
    public function domainIntelligence(string $domain): array
    {
        return $this->get(Endpoint::EmailIntelligence, ['domain' => $domain]);
    }

    /**
     * Score whichever of an email address or a domain name was supplied.
     *
     * The API gives `domain` priority when both are present.
     *
     * @return array<string, mixed>
     */
    public function intelligence(?string $email = null, ?string $domain = null): array
    {
        if (blank($email) && blank($domain)) {
            throw WhoisJsonException::missingEmailOrDomain();
        }

        return $this->get(Endpoint::EmailIntelligence, [
            'email' => $email,
            'domain' => $domain,
        ]);
    }

    /**
     * Perform a request and return the decoded JSON body.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws WhoisJsonException
     */
    public function get(Endpoint|string $endpoint, array $query = []): array
    {
        return (array) $this->response($endpoint, $query)->json();
    }

    /**
     * Perform a request and return the raw XML body.
     *
     * @param  array<string, mixed>  $query
     *
     * @throws WhoisJsonException
     */
    public function xml(Endpoint|string $endpoint, array $query = []): string
    {
        return $this->response($endpoint, $query, Format::Xml)->body();
    }

    /**
     * Perform a request and return the verified response.
     *
     * @param  array<string, mixed>  $query
     *
     * @throws WhoisJsonException
     */
    public function response(Endpoint|string $endpoint, array $query = [], ?Format $format = null): Response
    {
        $query = $this->query($query, $format);

        $response = $this->request($format)->get(
            url: $endpoint instanceof Endpoint ? $endpoint->path() : ltrim($endpoint, '/'),
            query: $query,
        );

        return $this->verify($endpoint, $query, $response);
    }

    /**
     * A configured, ready-to-send client — the escape hatch for anything not wrapped here.
     */
    public function request(?Format $format = null): PendingRequest
    {
        if (blank($this->apiKey)) {
            throw WhoisJsonException::missingApiKey();
        }

        return $this->http
            ->baseUrl($this->apiBaseUrl)
            ->accept(($format ?? Format::Json)->accepts())
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->withHeader('Authorization', $this->authorization())
            ->retry(
                times: max(1, $this->retryTimes),
                sleepMilliseconds: $this->retrySleep,
                when: $this->shouldRetry(...),
                throw: false,
            )
            // Paced as middleware rather than in `response()` so the escape hatch
            // and every retry attempt are throttled too — each is its own API call.
            ->when($this->limiter()->enabled(), fn (PendingRequest $request): PendingRequest => $request
                ->withRequestMiddleware(function (RequestInterface $request): RequestInterface {
                    $this->limiter()->throttle();

                    return $request;
                })
            );
    }

    /**
     * The client-side pacer for the plan's per-minute rate limit.
     */
    public function limiter(): RateLimiter
    {
        return $this->limiter ??= new RateLimiter(
            cache: $this->cache->store(),
            key: 'whoisjson:rate-limit:'.hash('xxh128', $this->apiBaseUrl.$this->apiKey),
            perMinute: $this->apiRateLimit,
        );
    }

    /**
     * Return a copy that bypasses the API's 3-hour cache. Costs 2x credits per call.
     */
    public function fresh(bool $forceRefresh = true): static
    {
        $clone = clone $this;
        $clone->forceRefresh = $forceRefresh;

        return $clone;
    }

    /**
     * Return a copy that uses the API's cached data, whatever the configured default.
     */
    public function cached(): static
    {
        return $this->fresh(false);
    }

    /**
     * The response to the most recent call made through this instance.
     */
    public function lastResponse(): ?Response
    {
        return $this->lastResponse;
    }

    /**
     * Calls left in the current billing period, read from the `Remaining-Requests` header.
     */
    public function remainingRequests(): ?int
    {
        return $this->remainingRequestsFrom($this->lastResponse);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function query(array $query, ?Format $format = null): array
    {
        if ($format instanceof Format) {
            $query['format'] = $format->value;
        }

        if ($this->forceRefresh) {
            $query['_forceRefresh'] = 1;
        }

        return array_filter($query, static fn (mixed $value): bool => filled($value));
    }

    /**
     * Record the response, reject anything the API flagged as an error, and
     * announce what survived.
     *
     * @param  array<string, mixed>  $query
     *
     * @throws WhoisJsonException
     */
    protected function verify(Endpoint|string $endpoint, array $query, Response $response): Response
    {
        $this->lastResponse = $response;

        if ($response->failed() || filled(data_get($response->json(), 'error'))) {
            throw WhoisJsonException::fromResponse($response);
        }

        // Resolved through the container at dispatch time rather than injected,
        // so `Event::fake()` still intercepts however early the singleton was built.
        event(new ResponseVerified($endpoint, $query, $response));

        return $response;
    }

    protected function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        return $exception instanceof RequestException
            && in_array($exception->response->status(), self::RETRYABLE_STATUSES, true);
    }

    protected function authorization(): string
    {
        // `finish` keeps a token type that already carries its separator (e.g. "Bearer ")
        // intact while guaranteeing exactly one occurrence of the key.
        return str($this->apiTokenType)->finish($this->apiKey)->value();
    }
}
