# Times are rendered in the bidding zone's timezone, not the viewer's

The server works exclusively in UTC and sends UTC instants to the browser, but the browser formats them in a fixed Europe/Tallinn — never in the viewer's own timezone. A price belongs to an Estonian local day; a viewer in Warsaw formatting the same series locally would see the selected day start at 23:00 of the previous date, and the cheapest window on screen would disagree with the one in the submission email.

## Consequences

Every rendered time carries an explicit "times in Europe/Tallinn" label so the offset is never guessed. The display timezone is configuration, not a hardcoded string, because it belongs to the bidding zone — if a second zone is ever added, it travels with it.
