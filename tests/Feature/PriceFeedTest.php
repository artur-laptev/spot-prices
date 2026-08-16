<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Pricing\LocalDay;
use DateTimeZone;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PriceFeedTest extends TestCase
{
    #[Test]
    public function it_returns_the_indicators_and_periods_of_the_selected_day(): void
    {
        $day = $this->today();
        Http::fake(['*' => Http::response($this->payloadFor($day, 24, 3600))]);

        $response = $this->getJson(route('api.prices', ['date' => $day->toIsoDate(), 'window' => 3]));

        $response->assertOk();
        $response->assertJsonPath('date', $day->toIsoDate());
        $response->assertJsonPath('zone', 'ee');
        $response->assertJsonPath('resolution', '60 min');
        $response->assertJsonPath('windowHours', 3);
        $response->assertJsonCount(24, 'periods');

        $this->assertEqualsWithDelta(10.0, $response->json('indicators.minimum.eurPerMwh'), 0.001);
        $this->assertEqualsWithDelta(33.0, $response->json('indicators.maximum.eurPerMwh'), 0.001);
        $this->assertEqualsWithDelta(10.0, $response->json('periods.0.exchangeEurPerMwh'), 0.001);
    }

    #[Test]
    public function it_describes_a_quarter_hourly_day_without_assuming_24_periods(): void
    {
        $day = $this->today();
        Http::fake(['*' => Http::response($this->payloadFor($day, 96, 900))]);

        $response = $this->getJson(route('api.prices', ['date' => $day->toIsoDate()]));

        $response->assertOk();
        $response->assertJsonPath('resolution', '15 min');
        $response->assertJsonCount(96, 'periods');
    }

    #[Test]
    public function it_reports_an_unpublished_day_with_a_notice_and_no_indicators(): void
    {
        $tomorrow = $this->today()->addDays(1);
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['ee' => []]])]);

        $response = $this->getJson(route('api.prices', ['date' => $tomorrow->toIsoDate()]));

        $response->assertOk();
        $response->assertJsonPath('indicators', null);
        $response->assertJsonPath('periods', []);
        $response->assertJsonPath('notices.0.level', 'info');
    }

    #[Test]
    public function it_answers_with_service_unavailable_when_nothing_can_be_served(): void
    {
        Http::fake(['*' => Http::response(status: 503)]);

        $response = $this->getJson(route('api.prices', ['date' => $this->today()->toIsoDate()]));

        $response->assertServiceUnavailable();
        $response->assertJsonPath('notices.0.level', 'error');
    }

    #[Test]
    public function it_rejects_a_malformed_query(): void
    {
        $this->getJson(route('api.prices', ['date' => 'yesterday']))->assertUnprocessable();
        $this->getJson(route('api.prices', ['window' => 99]))->assertUnprocessable();
    }

    #[Test]
    public function it_rejects_a_day_outside_the_calendar(): void
    {
        $response = $this->getJson(route('api.prices', ['date' => '2019-01-01']));

        $response->assertUnprocessable();
        $response->assertJsonStructure(['error']);
    }

    #[Test]
    public function it_serves_a_cached_day_without_calling_elering_again(): void
    {
        $day = $this->today();
        Http::fake(['*' => Http::response($this->payloadFor($day, 24, 3600))]);

        $this->getJson(route('api.prices', ['date' => $day->toIsoDate()]))->assertOk();
        $this->getJson(route('api.prices', ['date' => $day->toIsoDate()]))->assertOk();

        Http::assertSentCount(1);
    }

    private function today(): LocalDay
    {
        return LocalDay::today(new DateTimeZone('Europe/Tallinn'));
    }

    /**
     * @return array{success: bool, data: array{ee: list<array{timestamp: int, price: float}>}}
     */
    private function payloadFor(LocalDay $day, int $count, int $stepSeconds): array
    {
        $rows = [];
        $start = $day->startsAt()->getTimestamp();

        for ($i = 0; $i < $count; $i++) {
            $rows[] = ['timestamp' => $start + $i * $stepSeconds, 'price' => 10.0 + $i];
        }

        return ['success' => true, 'data' => ['ee' => $rows]];
    }
}
