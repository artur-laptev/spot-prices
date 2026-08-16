# Spot Prices

Nord Pool day-ahead electricity prices for the Estonian bidding zone, fetched from Elering, summarised into daily indicators and rendered on a single page.

## Running it

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan serve
```

Then open <http://localhost:8000>. No database, no build step, no npm.

```bash
php artisan test          # no network needed
vendor/bin/pint           # PSR-12 formatting
vendor/bin/phpstan        # static analysis, level 6
```

GitHub Actions runs all three on every push and pull request, against PHP 8.3 and 8.4 (`.github/workflows/ci.yml`).

## What it does

- Fetches prices for a chosen day and shows every period in a table and a bar chart.
- Computes minimum, maximum, average, and the cheapest and most expensive window of a user-chosen length (1–6 h).
- Shows each price both as the raw exchange price excluding VAT and as a retail price including grid fee, seller margin and VAT.
- Sends the result to `jobs@qilowatt.eu` through the **Send result** form at the bottom of the page.

## JSON API

`GET /api/prices` returns the same figures the page renders, for anything that would rather not scrape HTML.

| Parameter | Required | Format | Default |
|---|---|---|---|
| `date` | no | `YYYY-MM-DD`, a local day in Europe/Tallinn | today |
| `window` | no | whole hours, 1–6 | 3 |

```bash
curl 'http://localhost:8000/api/prices?date=2026-08-15&window=3'
```

```json
{
  "date": "2026-08-15",
  "zone": "ee",
  "timezone": "Europe/Tallinn (EEST)",
  "resolution": "15 min",
  "windowHours": 3,
  "indicators": {
    "minimum": { "eurPerMwh": 6.49, "exchangeSntKwh": 0.65, "retailSntKwhInclVat": 7 },
    "maximum": { "eurPerMwh": 71.01, "exchangeSntKwh": 7.1, "retailSntKwhInclVat": 15.01 },
    "average": { "eurPerMwh": 23.14, "exchangeSntKwh": 2.31, "retailSntKwhInclVat": 9.07 },
    "cheapestWindow": {
      "range": "13:30–16:30", "startsAt": "13:30",
      "eurPerMwh": 10.23, "exchangeSntKwh": 1.02, "retailSntKwhInclVat": 7.47
    },
    "priciestWindow": {
      "range": "18:30–21:30", "startsAt": "18:30",
      "eurPerMwh": 50.52, "exchangeSntKwh": 5.05, "retailSntKwhInclVat": 12.46
    }
  },
  "periods": [
    {
      "startsAtUtc": "2026-08-14T21:00:00+00:00",
      "label": "00:00",
      "range": "00:00–00:15",
      "exchangeEurPerMwh": 61,
      "exchangeSntKwh": 6.1,
      "retailSntKwhInclVat": 13.76,
      "belowAverage": false,
      "inCheapestWindow": false,
      "inPriciestWindow": false
    }
  ],
  "notices": []
}
```

`eurPerMwh` is the exchange price excluding VAT, exactly as Elering published it. `exchangeSntKwh` is that same price in cents per kWh, still excluding VAT. `retailSntKwhInclVat` adds grid fee, seller margin and VAT. `startsAtUtc` is the only machine-facing instant; `label` and `range` are already in Europe/Tallinn. The number of `periods` follows the market's resolution and the local day — expect 96 on a normal quarter-hourly day, 92 or 100 on clock-change days.

| Status | When | Body |
|---|---|---|
| `200` | prices were served, from Elering or from cache | the document above |
| `200` | the day is not published yet | `indicators: null`, `periods: []`, one `info` notice |
| `422` | `date` or `window` is malformed, or the day falls outside the 30-day window the app covers | `{"error": "…"}` |
| `503` | Elering is unreachable and nothing is cached | same document with `indicators: null` and one `error` notice |

`notices` carries the same warnings the page shows, each with a `level` of `info`, `warning` or `error` — a stale-cache fallback, an incomplete day, or a day with no computable window. A `200` with a `warning` notice still holds usable prices. The endpoint is rate-limited to 60 requests a minute and shares the price cache with the page, so it never adds load on Elering.

## How it is put together

```
app/Domain/Pricing/       the calculations, plus a port for prices and one for the tariff
app/Infrastructure/       Elering HTTP client, caching price provider, config-backed tariff
app/Http/                 controllers, request parsing, PricePage and PriceFeed view models
docs/adr/                 why the non-obvious decisions were made
```

Nothing under `app/Domain` imports `Illuminate`. That is what lets the unit tests run without booting Laravel and without a network, and it is checked by the tests themselves living in plain PHPUnit test cases. `CONTEXT.md` holds the vocabulary the code commits to.

## Choices and trade-offs

**Period length is derived, never assumed.** Elering already returns 15-minute periods for the Estonian zone — the live response has 96 points per day, not 24. Every price point carries a duration computed from the smallest gap in the data, so 60-minute and 15-minute days work identically, and so do the 23- and 25-hour clock-change days. No period count is hard-coded; the single conversion from hours to periods reads the resolution off the data. See [ADR-0002](docs/adr/0002-period-length-is-derived-never-assumed.md).

**The server works in UTC; the browser renders Europe/Tallinn.** `config/app.php` pins the application timezone to UTC as a literal rather than reading it from the environment — which timezone the arithmetic happens in is a property of the code, not of the machine it runs on. The display timezone is separate configuration, `prices.display_timezone`. Times are never formatted in the viewer's own timezone: a price belongs to an Estonian local day, and a reviewer in another country would otherwise see the day start at 23:00 of the previous date. See [ADR-0005](docs/adr/0005-times-are-rendered-in-the-bidding-zone-timezone.md).

**Windows are searched on the exchange price excluding VAT.** Grid fee and margin are per-kWh constants and VAT is a multiplier, so neither can change which window is cheapest. The retail price exists for display. See [ADR-0003](docs/adr/0003-windows-are-scored-on-the-exchange-price.md).

**When Elering is down, stale cache is served, not an error.** 5-second timeout, two retries, then an expired cache entry for that day is shown with a banner saying how old it is. Day-ahead prices are immutable once published, so old data is still correct data. "Tomorrow is not published yet" is a separate, non-error message. See [ADR-0004](docs/adr/0004-stale-cache-is-served-when-elering-is-down.md).

**Incomplete days report no window.** If a period is missing, the table, chart and min/max/average still render over what arrived, labelled with how many of the expected periods came back — but the cheapest window is not computed across the hole. A window spanning a gap is a claim about a run of prices that never existed. Gaps are never interpolated or zero-filled. See [ADR-0006](docs/adr/0006-incomplete-days-report-no-window.md).

**The tariff comes from a port.** VAT rate, grid fee and margin are read through `TariffProvider`, implemented today by `StaticTariffProvider` over literals in `config/prices.php`. Moving them to a database or a billing service means one new class and one changed binding. See [ADR-0007](docs/adr/0007-tariff-is-fetched-through-a-port.md).

**Rounding happens only at the edge.** The domain carries full precision; the view model rounds to two decimals.

**Personal data is not remembered.** Name, email and phone are kept out of session flashing and out of the logs; only the fact that an email was sent is recorded. The submit route is rate-limited to five requests a minute.

## Configuration

Prices, tariff, cache TTLs and the Elering endpoint live in `config/prices.php` as plain values. `.env` carries only what is environment-specific or secret — mail credentials above all. Both TTLs are configurable: past days are cached for 30 days because published prices never change, today and tomorrow for 15 minutes.

`CACHE_STORE=file` and `SESSION_DRIVER=file` are not decoration: Laravel defaults both to `database`, and this app has no database. Every other key in `.env.example` either carries a secret or changes behaviour — a key that matches the framework default is not worth the reader's attention.

Mail is sent synchronously via SMTP from `.env`; there is no queue, so no worker is needed to run the app. `.env.example` carries a Gmail-shaped configuration and a commented Mailtrap alternative.

## What is not here

- Two of the five bonus tasks, the two the brief allows: the JSON endpoint and CI. Battery simulation, multi-zone comparison and the 30-day history are not here.
- No Docker. `php artisan serve` is the one supported way to run it; a second path is a second thing that can break on a reviewer's machine.
- No database, no queue, no authentication — none are needed.
- The repository URL and commit SHA are typed into the submit form by hand, so the app never shells out to git.

## What would come next

- Multi-zone comparison: the client already receives `fi`, `lv` and `lt` in every response and throws them away. The work is turning `PriceSnapshot` and the cache keys plural, not fetching more data.
- The 30-day average behind the selected day — one extra range request to Elering, cached like the rest.
- PHPStan level 8. Level 6 passes clean today; the remaining eight errors at level 8 are `mixed` coming out of `config()` and nullable windows in test assertions.
