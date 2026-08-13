<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

interface PriceProvider
{
    /**
     * @throws PricesUnavailable
     */
    public function snapshotFor(LocalDay $day): PriceSnapshot;
}
