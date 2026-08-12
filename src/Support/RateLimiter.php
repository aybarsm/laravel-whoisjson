<?php

declare(strict_types=1);

namespace Aybarsm\Laravel\WhoisJson\Support;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Sleep;

/**
 * Client-side pacing for the whoisjson.com per-minute rate limit.
 *
 * The API enforces its plan limit on a rolling 60-second window, so this
 * mirrors that shape rather than a fixed bucket: a fixed window would let two
 * back-to-back bursts straddle the boundary and still earn a 429.
 *
 * @see https://whoisjson.com/bulk-whois-api
 */
class RateLimiter
{
    /**
     * The width of the rolling window, in milliseconds.
     */
    protected const WINDOW = 60_000;

    /**
     * How long to wait for the reservation lock, in seconds.
     */
    protected const LOCK_TIMEOUT = 10;

    public function __construct(
        protected readonly Repository $cache,
        protected readonly string $key,
        protected readonly int $perMinute,
    ) {}

    /**
     * Claim a slot in the current window, sleeping until one frees up.
     */
    public function throttle(): void
    {
        if (! $this->enabled()) {
            return;
        }

        while (($wait = $this->reserve()) > 0) {
            Sleep::for($wait)->milliseconds();
        }
    }

    public function enabled(): bool
    {
        return $this->perMinute > 0;
    }

    /**
     * Calls already made in the current window.
     */
    public function used(): int
    {
        return count($this->hits(now()->getTimestampMs()));
    }

    /**
     * Calls still allowed in the current window.
     */
    public function remaining(): int
    {
        return $this->enabled()
            ? max(0, $this->perMinute - $this->used())
            : PHP_INT_MAX;
    }

    /**
     * Take a slot, or report how many milliseconds until the next one frees.
     *
     * The lock is held only for this bookkeeping — never across the sleep —
     * so concurrent workers queue for the reservation, not for each other's waits.
     */
    protected function reserve(): int
    {
        $reserve = function (): int {
            $now = now()->getTimestampMs();
            $hits = $this->hits($now);

            if (count($hits) >= $this->perMinute) {
                return max(1, $hits[0] + static::WINDOW - $now);
            }

            $hits[] = $now;
            $this->cache->put($this->key, $hits, now()->addMilliseconds(static::WINDOW));

            return 0;
        };

        $store = $this->cache->getStore();

        return $store instanceof LockProvider
            ? $store->lock("{$this->key}:lock", static::LOCK_TIMEOUT)->block(static::LOCK_TIMEOUT, $reserve)
            : $reserve();
    }

    /**
     * Timestamps of the calls still inside the window, oldest first.
     *
     * @return array<int, int>
     */
    protected function hits(int $now): array
    {
        return array_values(array_filter(
            (array) $this->cache->get($this->key, []),
            static fn (mixed $hit): bool => is_int($hit) && $hit > $now - static::WINDOW,
        ));
    }
}
