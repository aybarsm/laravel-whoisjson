<?php

declare(strict_types=1);

namespace Aybarsm\Laravel\WhoisJson\Concerns;

use Illuminate\Http\Client\Response;

trait ReadsRemainingRequests
{
    /**
     * Calls left in the current billing period, as reported by the API.
     *
     * Every reply carries the header, successful or not.
     *
     * @see https://whoisjson.com/documentation#quick-start
     */
    protected function remainingRequestsFrom(?Response $response): ?int
    {
        $remaining = $response?->header('Remaining-Requests');

        return is_numeric($remaining) ? (int) $remaining : null;
    }
}
