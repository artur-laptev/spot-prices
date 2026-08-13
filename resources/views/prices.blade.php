<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Spot prices — {{ $page->isoDate }}</title>
    <style>
        :root {
            --bg: #f6f7f9;
            --surface: #ffffff;
            --ink: #16191d;
            --muted: #63696f;
            --line: #dfe3e8;
            --cheap: #1c7c4a;
            --cheap-bg: #e7f5ec;
            --pricey: #b3261e;
            --pricey-bg: #fdecea;
            --accent: #1b4fd8;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 1.5rem 1rem 4rem;
            background: var(--bg);
            color: var(--ink);
            font: 16px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif;
        }

        main { max-width: 60rem; margin: 0 auto; }

        h1 { font-size: 1.5rem; margin: 0 0 .25rem; }
        h2 { font-size: 1.1rem; margin: 2rem 0 .75rem; }

        .subtitle { color: var(--muted); margin: 0 0 1.5rem; font-size: .9rem; }

        .panel {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: .75rem;
            padding: 1rem;
        }

        .controls { display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end; }
        .controls label { display: block; font-size: .8rem; color: var(--muted); margin-bottom: .25rem; }

        input, select, button, textarea {
            font: inherit;
            padding: .5rem .625rem;
            border: 1px solid var(--line);
            border-radius: .5rem;
            background: var(--surface);
            color: inherit;
        }

        button {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
            cursor: pointer;
        }

        button.secondary { background: var(--surface); border-color: var(--line); color: var(--ink); }

        .notice { padding: .75rem 1rem; border-radius: .5rem; margin: 1rem 0; border: 1px solid; }
        .notice-info { background: #eef3ff; border-color: #c3d3ff; }
        .notice-warning { background: #fff6e5; border-color: #ffdd9e; }
        .notice-error { background: var(--pricey-bg); border-color: #f5c3bf; }
        .notice-success { background: var(--cheap-bg); border-color: #bfe3cd; }

        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: .75rem; }
        .card { background: var(--surface); border: 1px solid var(--line); border-radius: .75rem; padding: .875rem; }
        .card .label { font-size: .75rem; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
        .card .value { font-size: 1.35rem; font-weight: 600; margin-top: .25rem; }
        .card .aside { font-size: .8rem; color: var(--muted); }
        .card.cheap { border-color: var(--cheap); background: var(--cheap-bg); }
        .card.pricey { border-color: var(--pricey); background: var(--pricey-bg); }

        .chart-wrap { position: relative; height: 22rem; }

        .table-scroll { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
        th, td { padding: .5rem .625rem; text-align: right; border-bottom: 1px solid var(--line); white-space: nowrap; }
        th:first-child, td:first-child { text-align: left; }
        thead th { font-size: .75rem; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
        tr.below td { color: var(--cheap); }
        tr.above td { color: var(--pricey); }
        tr.in-cheapest { background: var(--cheap-bg); font-weight: 600; }
        tr.in-priciest { background: var(--pricey-bg); }

        .legend { display: flex; flex-wrap: wrap; gap: 1rem; font-size: .8rem; color: var(--muted); margin: .75rem 0; }
        .swatch { display: inline-block; width: .75rem; height: .75rem; border-radius: .2rem; margin-right: .35rem; vertical-align: -1px; }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); gap: .75rem; }
        .form-grid label { display: block; font-size: .8rem; color: var(--muted); margin-bottom: .25rem; }
        .form-grid input { width: 100%; }
        .field-error { color: var(--pricey); font-size: .8rem; margin-top: .25rem; }

        details.submission { margin-top: 1rem; }
        details.submission > summary { cursor: pointer; font-weight: 600; padding: .5rem 0; }

        @media (max-width: 30rem) {
            .controls { flex-direction: column; align-items: stretch; }
            .chart-wrap { height: 16rem; }
        }
    </style>
</head>
<body>
<main>
    <h1>Nord Pool day-ahead prices</h1>
    <p class="subtitle">
        {{ $page->headline }} · all times in {{ $page->timezoneLabel }}
        @if ($page->resolutionLabel) · {{ $page->resolutionLabel }} periods @endif
    </p>

    <form class="panel controls" method="get" action="{{ route('prices') }}">
        <div>
            <label for="date">Date</label>
            <input type="date" id="date" name="date" value="{{ $page->isoDate }}"
                   min="{{ $page->minIsoDate }}" max="{{ $page->maxIsoDate }}">
        </div>
        <div>
            <label for="window">Window length</label>
            <select id="window" name="window">
                @foreach ($windowOptions as $hours)
                    <option value="{{ $hours }}" @selected((int) $page->windowHours === $hours)>{{ $hours }} h</option>
                @endforeach
            </select>
        </div>
        <button type="submit">Show</button>
    </form>

    @if (session('submission_status'))
        <div class="notice notice-success">{{ session('submission_status') }}</div>
    @endif

    @if (session('submission_error'))
        <div class="notice notice-error">{{ session('submission_error') }}</div>
    @endif

    @foreach ($page->notices as $notice)
        <div class="notice notice-{{ $notice['level'] }}">{{ $notice['message'] }}</div>
    @endforeach

    @if ($page->summary !== [])
        <h2>Indicators</h2>
        <div class="cards">
            <div class="card">
                <div class="label">Average</div>
                <div class="value">{{ number_format($page->summary['average']['retailSntKwhInclVat'], 2) }} snt/kWh</div>
                <div class="aside">{{ number_format($page->summary['average']['exchangeSntKwh'], 2) }} exchange, ex. VAT</div>
            </div>
            <div class="card">
                <div class="label">Minimum</div>
                <div class="value">{{ number_format($page->summary['minimum']['retailSntKwhInclVat'], 2) }} snt/kWh</div>
                <div class="aside">{{ number_format($page->summary['minimum']['exchangeSntKwh'], 2) }} exchange, ex. VAT</div>
            </div>
            <div class="card">
                <div class="label">Maximum</div>
                <div class="value">{{ number_format($page->summary['maximum']['retailSntKwhInclVat'], 2) }} snt/kWh</div>
                <div class="aside">{{ number_format($page->summary['maximum']['exchangeSntKwh'], 2) }} exchange, ex. VAT</div>
            </div>
            @if ($page->summary['cheapestWindow'])
                <div class="card cheap">
                    <div class="label">Cheapest {{ (int) $page->windowHours }} h</div>
                    <div class="value">{{ $page->summary['cheapestWindow']['range'] }}</div>
                    <div class="aside">{{ number_format($page->summary['cheapestWindow']['retailSntKwhInclVat'], 2) }} snt/kWh average</div>
                </div>
            @endif
            @if ($page->summary['priciestWindow'])
                <div class="card pricey">
                    <div class="label">Most expensive {{ (int) $page->windowHours }} h</div>
                    <div class="value">{{ $page->summary['priciestWindow']['range'] }}</div>
                    <div class="aside">{{ number_format($page->summary['priciestWindow']['retailSntKwhInclVat'], 2) }} snt/kWh average</div>
                </div>
            @endif
        </div>
    @endif

    @if ($page->hasRows())
        <h2>Chart</h2>
        <div class="panel">
            <div class="chart-wrap"><canvas id="prices-chart"></canvas></div>
            <div class="legend">
                <span><span class="swatch" style="background:#1c7c4a"></span>Cheapest window</span>
                <span><span class="swatch" style="background:#7fbf9b"></span>Below average</span>
                <span><span class="swatch" style="background:#e0a1a1"></span>Above average</span>
                <span><span class="swatch" style="background:#b3261e"></span>Most expensive window</span>
            </div>
            <noscript>Charts need JavaScript. The full table below carries the same numbers.</noscript>
        </div>

        <h2>All periods</h2>
        <div class="panel table-scroll">
            <table>
                <thead>
                <tr>
                    <th>Period</th>
                    <th>EUR/MWh</th>
                    <th>Exchange, ex. VAT</th>
                    <th>Retail, incl. VAT</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($page->rows as $row)
                    <tr class="{{ $row['belowAverage'] ? 'below' : 'above' }} {{ $row['inCheapestWindow'] ? 'in-cheapest' : '' }} {{ $row['inPriciestWindow'] ? 'in-priciest' : '' }}">
                        <td>{{ $row['range'] }}</td>
                        <td>{{ number_format($row['exchangeEurPerMwh'], 2) }}</td>
                        <td>{{ number_format($row['exchangeSntKwh'], 2) }}</td>
                        <td>{{ number_format($row['retailSntKwhInclVat'], 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <details class="submission" @if ($errors->any() || session('submission_error')) open @endif>
            <summary>Send result</summary>
            <form class="panel" method="post" action="{{ route('submit') }}">
                @csrf
                <input type="hidden" name="date" value="{{ $page->isoDate }}">
                <input type="hidden" name="window" value="{{ (int) $page->windowHours }}">
                <div class="form-grid">
                    <div>
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" required>
                        @error('name') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required>
                        @error('email') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" required>
                        @error('phone') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="repository_url">Repository URL</label>
                        <input type="url" id="repository_url" name="repository_url" value="{{ old('repository_url') }}" required>
                        @error('repository_url') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="commit_sha">Commit SHA</label>
                        <input type="text" id="commit_sha" name="commit_sha" value="{{ old('commit_sha') }}" required>
                        @error('commit_sha') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                </div>
                <p style="margin:1rem 0 0"><button type="submit">Send to {{ config('prices.submission.recipient') }}</button></p>
            </form>
        </details>
    @endif
</main>

@if ($page->hasRows())
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.5.0/chart.umd.min.js"
            integrity="sha384-XcdcwHqIPULERb2yDEM4R0XaQKU3YnDsrTmjACBZyfdVVqjh6xQ4/DCMd7XLcA6Y"
            crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        const periods = {!! $page->chartPayload() !!};

        if (window.Chart) {
            const colorFor = (row) => {
                if (row.inCheapestWindow) return '#1c7c4a';
                if (row.inPriciestWindow) return '#b3261e';
                return row.belowAverage ? '#7fbf9b' : '#e0a1a1';
            };

            new Chart(document.getElementById('prices-chart'), {
                type: 'bar',
                data: {
                    labels: periods.map((row) => row.label),
                    datasets: [{
                        label: 'Retail, incl. VAT (snt/kWh)',
                        data: periods.map((row) => row.retailSntKwhInclVat),
                        backgroundColor: periods.map(colorFor),
                        borderWidth: 0,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: false, grid: { color: '#e7eaee' } },
                        x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 16 } },
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                title: (items) => periods[items[0].dataIndex].range,
                                label: (item) => [
                                    `Retail incl. VAT: ${item.parsed.y.toFixed(2)} snt/kWh`,
                                    `Exchange ex. VAT: ${periods[item.dataIndex].exchangeSntKwh.toFixed(2)} snt/kWh`,
                                ],
                            },
                        },
                    },
                },
            });
        }
    </script>
@endif
</body>
</html>
