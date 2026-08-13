# Stale cache is served when Elering is down

Elering is a public API with no availability guarantee, and the brief asks for a deliberate choice about failure. After a 5-second timeout and two retries, an expired cache entry for the requested day is served rather than discarded, with a banner stating how old it is. Day-ahead prices are immutable once published, so stale data is still correct data — only its completeness is in question.

## Consequences

Cache entries are read back after expiry instead of being evicted, so the cache abstraction must expose the stored timestamp. "Tomorrow's prices are not published yet" is a distinct, non-error outcome and must not be conflated with the API being unreachable.
