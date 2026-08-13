<?php

declare(strict_types=1);

namespace App\Infrastructure\Elering;

use App\Domain\Pricing\LocalDay;
use App\Domain\Pricing\PricesUnavailable;
use Illuminate\Http\Client\Factory as HttpClient;
use Throwable;

final readonly class EleringClient
{
    public function __construct(
        private HttpClient $http,
        private string $baseUrl,
        private int $timeoutSeconds,
        private int $retries,
        private int $retryDelayMs,
    ) {}

    /**
     * @return list<array{timestamp: int, price: float}>
     */
    public function fetchDay(LocalDay $day, string $zone): array
    {
        try {
            $response = $this->http
                ->timeout($this->timeoutSeconds)
                ->retry($this->retries + 1, $this->retryDelayMs, throw: false)
                ->acceptJson()
                ->get($this->baseUrl.'/api/nps/price', [
                    'start' => $day->startsAt()->format('Y-m-d\TH:i:s.000\Z'),
                    'end' => $day->endsAt()->modify('-1 second')->format('Y-m-d\TH:i:s.999\Z'),
                ]);
        } catch (Throwable $e) {
            throw PricesUnavailable::for($day, $e);
        }

        if ($response->failed()) {
            throw PricesUnavailable::for($day);
        }

        $body = $response->json();

        if (! is_array($body) || ($body['success'] ?? false) !== true || ! isset($body['data'][$zone])) {
            throw PricesUnavailable::for($day);
        }

        return $this->normaliseRows($body['data'][$zone]);
    }

    /**
     * @return list<array{timestamp: int, price: float}>
     */
    private function normaliseRows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $normalised = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['timestamp'], $row['price'])) {
                continue;
            }

            $normalised[] = [
                'timestamp' => (int) $row['timestamp'],
                'price' => (float) $row['price'],
            ];
        }

        return $normalised;
    }
}
