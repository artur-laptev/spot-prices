<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use RuntimeException;
use Throwable;

final class PricesUnavailable extends RuntimeException
{
    public static function for(LocalDay $day, ?Throwable $previous = null): self
    {
        return new self("Prices for {$day->toIsoDate()} could not be retrieved.", previous: $previous);
    }
}
