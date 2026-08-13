# Laravel as the application framework

The brief explicitly warns against picking a framework for show and offers plain PHP as an equally acceptable option, so choosing Laravel needs a reason on record. We chose it because config-from-env, HTTP client with retries, file cache, validation, mail and a test harness all ship as first-class pieces we would otherwise hand-roll, leaving the time budget for the domain logic instead.

## Consequences

The framework must not leak into the domain. Price parsing, indicator calculation and window search live in framework-free classes with no Laravel imports, so the unit tests run without booting the application and without a network.
