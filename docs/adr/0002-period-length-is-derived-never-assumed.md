# Period length is derived, never assumed

The European day-ahead market moved to a 15-minute settlement period in October 2025, so Elering may return either 60- or 15-minute resolution, and Estonian local days hold 23 or 25 hours across clock changes. Every price point therefore carries an explicit duration, computed from the gap to the next point (the last point inherits the previous gap), and a price series is simply however many points the day contains.

## Consequences

No constant `24`, `3600` or `count($prices) === 24` may appear anywhere. Window length is expressed in hours by the user and converted to a period count at calculation time via the series' own resolution.
