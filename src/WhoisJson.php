<?php

declare(strict_types=1);

namespace Aybarsm\Laravel\WhoisJson;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Container\Attributes\Config;

#[Singleton]
class WhoisJson
{
    private PendingRequest $client;

    public function __construct(
        #[Config('whoisjson.api_base_url')] public readonly string $apiBaseUrl,
        #[Config('whoisjson.api_key')] private string $apiKey,
        #[Config('whoisjson.api_token_type')] public readonly string $apiTokenType,
    )
    {
        $this->client = new PendingRequest()
            ->baseUrl(url: $this->apiBaseUrl)
            ->acceptJson()
            ->withHeader(
                name: 'Authorization',
                value: str($this->apiTokenType)->trim()->finish($this->apiKey)->value(),
            );
    }

    public function request(): PendingRequest
    {
        return $this->client;
    }

    public function get(string $url, array|string|null $query = null): array
    {
        $result = $this->request()->get(
            url: $url,
            query: $query,
        );

        throw_if(
            $result->failed(),
            $result->reason()
        );

        return $result->json();
    }

    public function domainAvailability(string $domain): array
    {
        return $this->get(
            url: 'domain-availability',
            query: [
                'domain' => $domain,
            ],
        );
    }
}
