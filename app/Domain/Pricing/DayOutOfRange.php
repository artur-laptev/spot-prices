<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use RuntimeException;

final class DayOutOfRange extends RuntimeException
{
    public static function for(LocalDay $day): self
    {
        return new self("{$day->toIsoDate()} lies outside the range this page covers.");
    }
}
