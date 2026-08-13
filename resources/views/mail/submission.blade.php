<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"></head>
<body style="font: 15px/1.5 system-ui, -apple-system, 'Segoe UI', sans-serif; color: #16191d;">
<h2 style="margin:0 0 1rem;">Homework submission</h2>

<table cellpadding="6" style="border-collapse: collapse;">
    <tr><td><strong>Name</strong></td><td>{{ $submission->name }}</td></tr>
    <tr><td><strong>Email</strong></td><td>{{ $submission->email }}</td></tr>
    <tr><td><strong>Phone</strong></td><td>{{ $submission->phone }}</td></tr>
    <tr><td><strong>Repository</strong></td><td>{{ $submission->repositoryUrl }}</td></tr>
    <tr><td><strong>Commit</strong></td><td>{{ $submission->commitSha }}</td></tr>
</table>

<h3 style="margin:1.5rem 0 .5rem;">{{ $page->headline }} — zone {{ $zoneLabel }}</h3>
<p style="margin:0 0 1rem; color:#63696f;">Times in {{ $page->timezoneLabel }}. Prices in snt/kWh.</p>

@if ($page->summary === [])
    <p>No prices were published for this day.</p>
@else
    <table cellpadding="6" style="border-collapse: collapse; border: 1px solid #dfe3e8;">
        <tr style="background:#f6f7f9;">
            <th align="left">Indicator</th>
            <th align="right">Exchange, ex. VAT</th>
            <th align="right">Retail, incl. VAT</th>
        </tr>
        <tr>
            <td>Average</td>
            <td align="right">{{ number_format($page->summary['average']['exchangeSntKwh'], 2) }}</td>
            <td align="right">{{ number_format($page->summary['average']['retailSntKwhInclVat'], 2) }}</td>
        </tr>
        <tr>
            <td>Minimum</td>
            <td align="right">{{ number_format($page->summary['minimum']['exchangeSntKwh'], 2) }}</td>
            <td align="right">{{ number_format($page->summary['minimum']['retailSntKwhInclVat'], 2) }}</td>
        </tr>
        <tr>
            <td>Maximum</td>
            <td align="right">{{ number_format($page->summary['maximum']['exchangeSntKwh'], 2) }}</td>
            <td align="right">{{ number_format($page->summary['maximum']['retailSntKwhInclVat'], 2) }}</td>
        </tr>
        @if ($page->summary['cheapestWindow'])
            <tr>
                <td>Cheapest {{ (int) $page->windowHours }} h window, starting {{ $page->summary['cheapestWindow']['startsAt'] }}</td>
                <td align="right">{{ number_format($page->summary['cheapestWindow']['exchangeSntKwh'], 2) }}</td>
                <td align="right">{{ number_format($page->summary['cheapestWindow']['retailSntKwhInclVat'], 2) }}</td>
            </tr>
        @else
            <tr><td colspan="3">Cheapest window could not be computed: the day's data is incomplete.</td></tr>
        @endif
    </table>
@endif

<p style="margin-top:1.5rem; color:#63696f; font-size:13px;">
    Sent {{ $sentAt }} · PHP {{ $phpVersion }}
</p>
</body>
</html>
