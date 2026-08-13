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
php artisan test     # 55 tests, no network needed
vendor/bin/pint      # PSR-12 formatting
```

## What it does

- Fetches prices for a chosen day and shows every period in a table and a bar chart.
- Computes minimum, maximum, average, and the cheapest and most expensive window of a user-chosen length (1–6 h).
- Shows each price both as the raw exchange price excluding VAT and as a retail price including grid fee, seller margin and VAT.
- Sends the result to `jobs@qilowatt.eu` through the **Send result** form at the bottom of the page.

## How it is put together

```
app/Domain/Pricing/       framework-free: LocalDay, PricePoint, PriceSeries, Window,
                          WindowFinder, Indicators, Tariff, PriceCalendar + two ports
app/Infrastructure/       EleringClient (HTTP), CachedEleringPriceProvider (cache +
                          stale-if-error), StaticTariffProvider
app/Http/                 controllers, form request, PricePage view model
resources/views/          one page, one email (HTML + plain text)
docs/adr/                 why the non-obvious decisions were made
CONTEXT.md                the vocabulary this codebase commits to
```

Nothing under `app/Domain` imports `Illuminate`. That is what lets the unit tests run without booting Laravel and without a network, and it is checked by the tests themselves living in plain PHPUnit test cases.

## Choices and trade-offs

**Period length is derived, never assumed.** Elering already returns 15-minute periods for the Estonian zone — the live response has 96 points per day, not 24. Every price point carries a duration computed from the smallest gap in the data, so 60-minute and 15-minute days work identically, and so do the 23- and 25-hour clock-change days. There is no `24` and no `3600` in the calculation code. See [ADR-0002](docs/adr/0002-period-length-is-derived-never-assumed.md).

**The server works in UTC; the browser renders Europe/Tallinn.** `APP_TIMEZONE=UTC`, and the display timezone is separate configuration. Times are never formatted in the viewer's own timezone: a price belongs to an Estonian local day, and a reviewer in another country would otherwise see the day start at 23:00 of the previous date. See [ADR-0005](docs/adr/0005-times-are-rendered-in-the-bidding-zone-timezone.md).

**Windows are searched on the exchange price excluding VAT.** Grid fee and margin are per-kWh constants and VAT is a multiplier, so neither can change which window is cheapest. The retail price exists for display. See [ADR-0003](docs/adr/0003-windows-are-scored-on-the-exchange-price.md).

**When Elering is down, stale cache is served, not an error.** 5-second timeout, two retries, then an expired cache entry for that day is shown with a banner saying how old it is. Day-ahead prices are immutable once published, so old data is still correct data. "Tomorrow is not published yet" is a separate, non-error message. See [ADR-0004](docs/adr/0004-stale-cache-is-served-when-elering-is-down.md).

**Incomplete days report no window.** If a period is missing, the table, chart and min/max/average still render over what arrived, labelled with how many of the expected periods came back — but the cheapest window is not computed across the hole. A window spanning a gap is a claim about a run of prices that never existed. Gaps are never interpolated or zero-filled. See [ADR-0006](docs/adr/0006-incomplete-days-report-no-window.md).

**The tariff comes from a port.** VAT rate, grid fee and margin are read through `TariffProvider`, implemented today by `StaticTariffProvider` over literals in `config/prices.php`. Moving them to a database or a billing service means one new class and one changed binding. See [ADR-0007](docs/adr/0007-tariff-is-fetched-through-a-port.md).

**Rounding happens only at the edge.** The domain carries full precision; values are rounded to two decimals in the view model. Averaging rounded numbers and rounding an average disagree, and the difference shows up exactly where correctness is being judged.

**Personal data is deliberately not remembered.** Name, email and phone are excluded from session flashing, so a validation error clears those three fields instead of storing them server-side. Only the fact that an email was sent is logged, never its contents.

## Configuration

Prices, tariff, cache TTLs and the Elering endpoint live in `config/prices.php` as plain values. `.env` carries only what is environment-specific or secret — mail credentials above all. Both TTLs are configurable: past days are cached for 30 days because published prices never change, today and tomorrow for 15 minutes.

Mail is sent synchronously via SMTP from `.env`; there is no queue, so no worker is needed to run the app. `.env.example` carries a Gmail-shaped configuration and a commented Mailtrap alternative.

## What is not here

- No bonus tasks. The brief says bonuses do not count when the core is thin, so the time went into the core.
- No Docker. `php artisan serve` is the one supported way to run it; a second path is a second thing that can break on a reviewer's machine.
- No database, no queue, no authentication — none are needed.
- The repository URL and commit SHA are typed into the form by hand rather than detected from git, so the app never shells out and never depends on a `.git` directory being present.

## What would come next

- A JSON endpoint over the same view model — the data is already assembled in one place.
- CI running the test suite and PHPStan.
- Multi-zone comparison: the client already receives `fi`, `lv` and `lt` in every response and throws them away.
