<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

final readonly class PriceSeries
{
    /**
     * @param  list<PricePoint>  $points
     */
    private function __construct(
        private LocalDay $day,
        private array $points,
        private ?int $resolutionSeconds,
    ) {}

    public static function empty(LocalDay $day): self
    {
        return new self($day, [], null);
    }

    /**
     * @param  list<PriceReading>  $readings
     */
    public static function fromReadings(LocalDay $day, array $readings): self
    {
        $withinDay = array_filter($readings, static fn (PriceReading $r): bool => $day->contains($r->startsAt));
        $deduplicated = [];

        foreach ($withinDay as $reading) {
            $deduplicated[$reading->startsAt->getTimestamp()] = $reading;
        }

        ksort($deduplicated);
        $ordered = array_values($deduplicated);

        if ($ordered === []) {
            return self::empty($day);
        }

        $resolution = self::smallestGap($ordered) ?? self::secondsUntilEndOfDay($day, $ordered[0]);

        $points = array_map(
            static fn (PriceReading $r): PricePoint => new PricePoint($r->startsAt, $resolution, $r->eurPerMwh),
            $ordered,
        );

        return new self($day, $points, $resolution);
    }

    public function day(): LocalDay
    {
        return $this->day;
    }

    /**
     * @return list<PricePoint>
     */
    public function points(): array
    {
        return $this->points;
    }

    public function isEmpty(): bool
    {
        return $this->points === [];
    }

    public function count(): int
    {
        return count($this->points);
    }

    public function resolutionSeconds(): ?int
    {
        return $this->resolutionSeconds;
    }

    public function expectedCount(): ?int
    {
        if ($this->resolutionSeconds === null) {
            return null;
        }

        return intdiv($this->day->lengthInSeconds(), $this->resolutionSeconds);
    }

    public function missingCount(): int
    {
        return max(0, ($this->expectedCount() ?? 0) - $this->count());
    }

    public function isComplete(): bool
    {
        return ! $this->isEmpty() && $this->missingCount() === 0;
    }

    /**
     * @return list<float>
     */
    public function pricesEurPerMwh(): array
    {
        return array_map(static fn (PricePoint $p): float => $p->eurPerMwh, $this->points);
    }

    /**
     * @param  list<PriceReading>  $ordered
     */
    private static function smallestGap(array $ordered): ?int
    {
        $gaps = [];

        for ($i = 1, $n = count($ordered); $i < $n; $i++) {
            $gaps[] = $ordered[$i]->startsAt->getTimestamp() - $ordered[$i - 1]->startsAt->getTimestamp();
        }

        return $gaps === [] ? null : min($gaps);
    }

    private static function secondsUntilEndOfDay(LocalDay $day, PriceReading $only): int
    {
        return $day->endsAt()->getTimestamp() - $only->startsAt->getTimestamp();
    }
}
