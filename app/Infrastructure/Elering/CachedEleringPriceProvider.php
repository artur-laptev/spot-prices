<?php

declare(strict_types=1);

namespace App\Infrastructure\Elering;

use App\Domain\Pricing\LocalDay;
use App\Domain\Pricing\PriceProvider;
use App\Domain\Pricing\PriceReading;
use App\Domain\Pricing\PriceSeries;
use App\Domain\Pricing\PriceSnapshot;
use App\Domain\Pricing\PricesUnavailable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Carbon;

final readonly class CachedEleringPriceProvider implements PriceProvider
{
    public function __construct(
        private EleringClient $client,
        private Cache $cache,
        private string $zone,
        private int $unsettledTtlSeconds,
        private int $retentionSeconds,
        private int $upstreamDownTtlSeconds,
    ) {}

    public function snapshotFor(LocalDay $day): PriceSnapshot
    {
        $cached = $this->readCache($day);

        if ($cached !== null && ! $this->hasGoneStale($cached, $day)) {
            return $this->toSnapshot($day, $cached, servedFromStaleCache: false);
        }

        if ($this->isUpstreamDown()) {
            if ($cached !== null) {
                return $this->toSnapshot($day, $cached, servedFromStaleCache: true);
            }

            throw PricesUnavailable::for($day);
        }

        try {
            $rows = $this->client->fetchDay($day, $this->zone);
        } catch (PricesUnavailable $e) {
            $this->markUpstreamDown();

            if ($cached !== null) {
                return $this->toSnapshot($day, $cached, servedFromStaleCache: true);
            }

            throw $e;
        }

        $entry = ['fetched_at' => $this->now()->getTimestamp(), 'rows' => $rows];
        $this->cache->put($this->cacheKey($day), $entry, $this->retentionSeconds);

        return $this->toSnapshot($day, $entry, servedFromStaleCache: false);
    }

    private function isUpstreamDown(): bool
    {
        return $this->upstreamDownTtlSeconds > 0
            && $this->cache->get($this->upstreamDownKey()) !== null;
    }

    private function markUpstreamDown(): void
    {
        if ($this->upstreamDownTtlSeconds > 0) {
            $this->cache->put($this->upstreamDownKey(), true, $this->upstreamDownTtlSeconds);
        }
    }

    private function upstreamDownKey(): string
    {
        return "elering:{$this->zone}:down";
    }

    /**
     * @return array{fetched_at: int, rows: list<array{timestamp: int, price: float}>}|null
     */
    private function readCache(LocalDay $day): ?array
    {
        $entry = $this->cache->get($this->cacheKey($day));

        if (! is_array($entry) || ! isset($entry['fetched_at'], $entry['rows'])) {
            return null;
        }

        return $entry;
    }

    /**
     * @param  array{fetched_at: int, rows: list<array{timestamp: int, price: float}>}  $entry
     */
    private function hasGoneStale(array $entry, LocalDay $day): bool
    {
        if ($this->isSettled($day)) {
            return false;
        }

        return $this->now()->getTimestamp() - $entry['fetched_at'] >= $this->unsettledTtlSeconds;
    }

    private function isSettled(LocalDay $day): bool
    {
        return $day->endsAt() <= $this->now();
    }

    /**
     * @param  array{fetched_at: int, rows: list<array{timestamp: int, price: float}>}  $entry
     */
    private function toSnapshot(LocalDay $day, array $entry, bool $servedFromStaleCache): PriceSnapshot
    {
        $readings = array_map(
            static fn (array $row): PriceReading => new PriceReading(
                (new DateTimeImmutable('@'.$row['timestamp']))->setTimezone(new DateTimeZone('UTC')),
                $row['price'],
            ),
            $entry['rows'],
        );

        return new PriceSnapshot(
            PriceSeries::fromReadings($day, $readings),
            (new DateTimeImmutable('@'.$entry['fetched_at']))->setTimezone(new DateTimeZone('UTC')),
            $servedFromStaleCache,
        );
    }

    private function cacheKey(LocalDay $day): string
    {
        return "elering:{$this->zone}:{$day->toIsoDate()}";
    }

    private function now(): DateTimeImmutable
    {
        return Carbon::now('UTC')->toDateTimeImmutable();
    }
}
