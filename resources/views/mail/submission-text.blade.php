HOMEWORK SUBMISSION

Name:       {{ $submission->name }}
Email:      {{ $submission->email }}
Phone:      {{ $submission->phone }}
Repository: {{ $submission->repositoryUrl }}
Commit:     {{ $submission->commitSha }}

{{ $page->headline }} — zone {{ $zoneLabel }}
Times in {{ $page->timezoneLabel }}. Prices in snt/kWh.
@if ($page->summary === [])

No prices were published for this day.
@else

Average: {{ number_format($page->summary['average']['exchangeSntKwh'], 2) }} exchange ex. VAT / {{ number_format($page->summary['average']['retailSntKwhInclVat'], 2) }} retail incl. VAT
Minimum: {{ number_format($page->summary['minimum']['exchangeSntKwh'], 2) }} exchange ex. VAT / {{ number_format($page->summary['minimum']['retailSntKwhInclVat'], 2) }} retail incl. VAT
Maximum: {{ number_format($page->summary['maximum']['exchangeSntKwh'], 2) }} exchange ex. VAT / {{ number_format($page->summary['maximum']['retailSntKwhInclVat'], 2) }} retail incl. VAT
@if ($page->summary['cheapestWindow'])
Cheapest {{ (int) $page->windowHours }} h window: {{ $page->summary['cheapestWindow']['range'] }}, {{ number_format($page->summary['cheapestWindow']['exchangeSntKwh'], 2) }} exchange ex. VAT / {{ number_format($page->summary['cheapestWindow']['retailSntKwhInclVat'], 2) }} retail incl. VAT
@else
Cheapest window could not be computed: the day's data is incomplete.
@endif
@endif

Sent {{ $sentAt }} · PHP {{ $phpVersion }}
