# The JSON feed is a presenter over the page view model, not a second path to the data

`GET /api/prices` answers with the figures the page already assembles. It could have been given its own use case reading the `PriceProvider` directly, but then two code paths would compute the indicators of a day, and the endpoint could disagree with the page it claims to mirror. Instead `PriceFeedController` calls the same `BuildPricePage`, and `PriceFeed` renames and reshapes the resulting `PricePage` into the response document.

The query string is parsed once, by `PriceQuery`, for both the page and the feed — same date format, same window bounds, same calendar. The two controllers differ only in what they do when parsing fails: the page redirects to its default view, the feed answers `422`.

## Consequences

An indicator can only be added in one place, and both surfaces get it. In exchange, `PriceFeed` is coupled to a view model named after the HTML page, and any field the page needs for its own rendering — the calendar bounds, the headline, the chart payload — has to be deliberately left out of the response rather than never existing. That is the cheaper of the two problems while there is one way to compute a day.

The response shape is pinned by the `@phpstan-type` array shapes declared on `PricePage` and imported by `PriceFeed`, so a change to a key is a static analysis failure rather than a silently broken client.
