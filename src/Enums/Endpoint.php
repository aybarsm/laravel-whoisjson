<?php

declare(strict_types=1);

namespace Aybarsm\Laravel\WhoisJson\Enums;

/**
 * The seven endpoints exposed by the whoisjson.com API.
 *
 * @see https://whoisjson.com/documentation#endpoints
 */
enum Endpoint: string
{
    case Whois = 'whois';
    case ReverseWhois = 'reverseWhois';
    case NsLookup = 'nslookup';
    case SslCertCheck = 'ssl-cert-check';
    case DomainAvailability = 'domain-availability';
    case Subdomains = 'subdomains';
    case EmailIntelligence = 'domain/email-intelligence';

    /**
     * The path, relative to the configured base url.
     */
    public function path(): string
    {
        return $this->value;
    }
}
