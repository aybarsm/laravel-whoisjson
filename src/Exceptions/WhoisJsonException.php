<?php

declare(strict_types=1);

namespace Aybarsm\Laravel\WhoisJson\Exceptions;

use Aybarsm\Laravel\WhoisJson\Concerns\ReadsRemainingRequests;
use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

class WhoisJsonException extends RuntimeException
{
    use ReadsRemainingRequests;

    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly ?Response $response = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    /**
     * Build an exception from a failed (or error-carrying) API response.
     *
     * The API answers errors with `{"error": "..."}`; every documented success
     * payload is error-free, so the key is a reliable failure marker even on 200.
     *
     * @see https://whoisjson.com/documentation#status-codes
     */
    public static function fromResponse(Response $response): self
    {
        $status = $response->status();

        $message = data_get($response->json(), 'error')
            ?: match ($status) {
                400 => 'Bad Request: missing or invalid query parameter.',
                401 => 'Unauthorized: the API key is missing or invalid.',
                403 => 'Access Denied: the account email address is not validated.',
                429 => 'Limit Exceeded: monthly quota or rate limit reached.',
                500 => 'Internal Error: server-side issue, retry later.',
                default => $response->reason() ?: 'The whoisjson.com request failed.',
            };

        return new self(
            message: "whoisjson.com [{$status}]: {$message}",
            status: $status,
            response: $response,
        );
    }

    public static function missingApiKey(): self
    {
        return new self(
            message: 'No whoisjson.com API key configured. Set WHOISJSON_API_KEY in your environment.',
        );
    }

    public static function missingEmailOrDomain(): self
    {
        return new self(
            message: 'The email-intelligence endpoint requires either an email address or a domain name.',
        );
    }

    /**
     * Whether the failure was caused by the monthly quota or per-minute rate limit.
     */
    public function rateLimited(): bool
    {
        return $this->status === 429;
    }

    /**
     * Number of API calls left in the current billing period, when reported.
     */
    public function remainingRequests(): ?int
    {
        return $this->remainingRequestsFrom($this->response);
    }
}
