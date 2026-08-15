<?php

declare(strict_types=1);

namespace Aybarsm\Laravel\WhoisJson\Events;

use Aybarsm\Laravel\WhoisJson\Concerns\ReadsRemainingRequests;
use Aybarsm\Laravel\WhoisJson\Enums\Endpoint;
use Illuminate\Http\Client\Response;

/**
 * Dispatched once a whoisjson.com response has been verified as successful,
 * immediately before it is handed back to the caller.
 *
 * Failed calls throw a WhoisJsonException instead and dispatch nothing.
 */
class ResponseVerified
{
    use ReadsRemainingRequests;

    /**
     * @param  array<string, mixed>  $query  The query as sent, including `format` and `_forceRefresh`.
     */
    public function __construct(
        public readonly Endpoint|string $endpoint,
        public readonly array $query,
        public readonly Response $response,
    ) {}

    /**
     * The decoded response body.
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        return (array) $this->response->json();
    }

    /**
     * Calls left in the current billing period, when reported.
     */
    public function remainingRequests(): ?int
    {
        return $this->remainingRequestsFrom($this->response);
    }

    /**
     * Whether the call bypassed the API's three-hour cache, at 2x credits.
     */
    public function forcedRefresh(): bool
    {
        return filled($this->query['_forceRefresh'] ?? null);
    }
}
