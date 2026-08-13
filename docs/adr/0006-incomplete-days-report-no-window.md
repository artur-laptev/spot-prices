# Incomplete days report no window

When Elering returns a day with a missing period, the page still renders the table, the chart and the min/max/mean over the periods that exist, labelled with how many of the expected periods arrived. The cheapest and most expensive window are not computed at all if the gap falls inside a candidate window — a window is a claim about a run of consecutive real prices, and one computed across a hole is a fact that never happened on the market.

## Consequences

Gaps are never interpolated or zero-filled, in the domain or in the chart. `Indicators` therefore models the windows as optional, and every caller — page, JSON view-model, submission email — has to handle their absence explicitly.
