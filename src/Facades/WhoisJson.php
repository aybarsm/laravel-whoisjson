<?php

declare(strict_types=1);

namespace Aybarsm\Laravel\WhoisJson\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array whois(string $domain)
 * @method static array reverseWhois(string $ip)
 * @method static array nsLookup(string $domain)
 * @method static array sslCertCheck(string $domain)
 * @method static array domainAvailability(string $domain)
 * @method static bool isAvailable(string $domain)
 * @method static array subdomains(string $domain)
 * @method static array emailIntelligence(string $email)
 * @method static array domainIntelligence(string $domain)
 * @method static array intelligence(?string $email = null, ?string $domain = null)
 * @method static array get(\Aybarsm\Laravel\WhoisJson\Enums\Endpoint|string $endpoint, array $query = [])
 * @method static string xml(\Aybarsm\Laravel\WhoisJson\Enums\Endpoint|string $endpoint, array $query = [])
 * @method static \Illuminate\Http\Client\Response response(\Aybarsm\Laravel\WhoisJson\Enums\Endpoint|string $endpoint, array $query = [], ?\Aybarsm\Laravel\WhoisJson\Enums\Format $format = null)
 * @method static \Illuminate\Http\Client\PendingRequest request(?\Aybarsm\Laravel\WhoisJson\Enums\Format $format = null)
 * @method static \Aybarsm\Laravel\WhoisJson\Support\RateLimiter limiter()
 * @method static \Aybarsm\Laravel\WhoisJson\WhoisJson fresh(bool $forceRefresh = true)
 * @method static \Aybarsm\Laravel\WhoisJson\WhoisJson cached()
 * @method static \Illuminate\Http\Client\Response|null lastResponse()
 * @method static int|null remainingRequests()
 * @method static int|null cachedRemainingRequests()
 * @method static bool forgetRemainingRequests()
 *
 * @see \Aybarsm\Laravel\WhoisJson\WhoisJson
 */
class WhoisJson extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Aybarsm\Laravel\WhoisJson\WhoisJson::class;
    }
}
