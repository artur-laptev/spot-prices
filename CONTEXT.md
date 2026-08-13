# Spot Prices

A single-page application that fetches Nord Pool day-ahead electricity prices for the Estonian bidding zone from Elering, derives daily indicators from them, and presents them to a human.

## Language

### Prices

**Exchange price**:
The raw day-ahead market price for one period, as published by Elering, in EUR/MWh excluding VAT. The only price that comes from outside the system.
_Avoid_: spot price, market price, nps price, raw price

**Retail price**:
What the consumer actually pays for one period: exchange price plus seller margin plus grid fee. Quoted in cents/kWh, with and without VAT.
_Avoid_: final price, total price, consumer price

**Seller margin**:
A configured per-kWh surcharge added by the electricity seller.
_Avoid_: markup, commission

**Grid fee**:
A configured per-kWh charge for network transmission, independent of the seller.
_Avoid_: network tariff, transmission cost, distribution fee

### Time

**Period**:
The smallest unit a price applies to — 60 minutes or 15 minutes depending on what the market published. Its length is derived from the data, never assumed.
_Avoid_: hour, slot, interval, tick

**Price point**:
One period paired with its exchange price: a start instant, a duration, and a value.
_Avoid_: price entry, data point, tick

**Price series**:
The ordered price points covering one local day. May hold 23, 24, or 25 hours' worth of periods on clock-change days, and reports any gap it contains rather than filling it.
_Avoid_: prices array, price list, dataset

**Local day**:
A calendar day in Europe/Tallinn — the day the user picks and reasons about. Starts at 21:00 UTC in summer and 22:00 UTC in winter.
_Avoid_: day, date, 24h period

### Indicators

**Window**:
A run of consecutive price points spanning a user-chosen length of 1–6 hours, scored by its mean exchange price. The cheapest and the most expensive window of a local day are the headline indicators.
_Avoid_: sliding window, block, range, cheapest hours

**Indicators**:
The set of figures derived from one price series: minimum, maximum, mean, cheapest window, most expensive window.
_Avoid_: stats, metrics, summary

**Incomplete day**:
A local day whose price series is missing one or more of the periods it should contain. Its gaps are never filled, and it carries no windows.
_Avoid_: partial day, broken data, missing prices

### Submission

**Submission**:
The candidate's own act of sending the homework result — name, contact details, repository link and commit SHA, plus the indicators of the day on screen — as an email from inside the application. The homework counts as delivered only once this email arrives.
_Avoid_: form, report, result
