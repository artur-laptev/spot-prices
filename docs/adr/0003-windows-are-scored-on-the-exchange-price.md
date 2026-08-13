# Windows are scored on the exchange price, not the retail price

A reader would reasonably expect the cheapest window to be searched over the price the consumer pays. We search over the exchange price excluding VAT instead: the seller margin and grid fee are per-kWh constants across every period of the day and VAT is a single multiplier, so neither can change the ordering of windows. The result is identical, and scoring the raw input keeps the window search independent of tariff configuration.

## Consequences

The retail price exists purely for display. If a future tariff becomes time-of-use — different grid fees for day and night — this reasoning breaks and the window search must move to the retail price.
