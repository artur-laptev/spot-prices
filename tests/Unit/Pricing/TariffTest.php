<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Domain\Pricing\Tariff;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TariffTest extends TestCase
{
    #[Test]
    public function one_hundred_eur_per_mwh_is_ten_cents_per_kwh(): void
    {
        $this->assertSame(10.0, Tariff::eurPerMwhToSntKwh(100.0));
    }

    #[Test]
    public function the_retail_price_adds_the_grid_fee_and_the_margin(): void
    {
        $tariff = new Tariff(vatRate: 0.24, gridFeeSntKwh: 4.5, sellerMarginSntKwh: 0.5);

        $this->assertSame(15.0, $tariff->retailSntKwh(100.0));
    }

    #[Test]
    public function vat_applies_to_the_whole_retail_price(): void
    {
        $tariff = new Tariff(vatRate: 0.24, gridFeeSntKwh: 4.5, sellerMarginSntKwh: 0.5);

        $this->assertSame(18.6, round($tariff->withVat($tariff->retailSntKwh(100.0)), 10));
    }

    #[Test]
    public function negative_exchange_prices_convert_without_special_casing(): void
    {
        $tariff = new Tariff(vatRate: 0.24, gridFeeSntKwh: 4.5, sellerMarginSntKwh: 0.5);

        $this->assertSame(-2.0, $tariff->exchangeSntKwh(-20.0));
        $this->assertSame(3.0, $tariff->retailSntKwh(-20.0));
    }

    #[Test]
    public function a_zero_vat_tariff_leaves_the_price_alone(): void
    {
        $tariff = new Tariff(vatRate: 0.0, gridFeeSntKwh: 0.0, sellerMarginSntKwh: 0.0);

        $this->assertSame(5.0, $tariff->withVat($tariff->retailSntKwh(50.0)));
    }
}
