# Tariff is fetched through a port, not read from config

VAT rate, grid fee and seller margin are the values most likely to stop being ours: in a real product they arrive from a billing system, a contract per customer, or a database row that changes without a deploy. The domain therefore asks a `TariffProvider` for the current `Tariff` on every request, and the only implementation today, `StaticTariffProvider`, is handed literal values from `config/prices.php`.

## Consequences

Swapping the source means writing a second implementation and changing one binding in `AppServiceProvider` — nothing in the domain or the controllers moves. The provider is deliberately called per request rather than resolved once at boot, so a future source that changes between requests behaves correctly without further edits.
