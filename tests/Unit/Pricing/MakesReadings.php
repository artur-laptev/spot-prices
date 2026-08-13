<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Domain\Pricing\LocalDay;
use App\Domain\Pricing\PriceReading;

trait MakesReadings
{
    /**
     * @return list<PriceReading>
     */
    private function hourlyReadings(LocalDay $day, int $count, ?array $prices = null): array
    {
        return $this->readings($day, $count, 3600, $prices);
    }

    /**
     * @param  list<float>|null  $prices
     * @return list<PriceReading>
     */
    private function readings(LocalDay $day, int $count, int $stepSeconds, ?array $prices = null): array
    {
        $readings = [];
        $start = $day->startsAt();

        for ($i = 0; $i < $count; $i++) {
            $readings[] = new PriceReading(
                $start->modify('+'.($i * $stepSeconds).' seconds'),
                $prices[$i] ?? (float) $i,
            );
        }

        return $readings;
    }
}
