<?php

declare(strict_types=1);

namespace App\Infrastructure\Tariffs;

use App\Domain\Pricing\Tariff;
use App\Domain\Pricing\TariffProvider;

final readonly class StaticTariffProvider implements TariffProvider
{
    public function __construct(
        private float $vatRate,
        private float $gridFeeSntKwh,
        private float $sellerMarginSntKwh,
    ) {}

    public function current(): Tariff
    {
        return new Tariff($this->vatRate, $this->gridFeeSntKwh, $this->sellerMarginSntKwh);
    }
}
