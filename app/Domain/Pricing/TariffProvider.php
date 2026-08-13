<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

interface TariffProvider
{
    public function current(): Tariff;
}
