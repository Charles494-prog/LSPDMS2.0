<!doctype html>
<html>
<head><meta charset="utf-8"><title>{{ $title }}</title>
<style>@page{margin:.55in}body{font-family:Arial,sans-serif;font-size:10pt}h1{text-align:center;font-size:16pt}p{margin:4px 0 12px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #222;padding:6px;text-align:left}th{background:#e8edf5}thead{display:table-header-group}.center{text-align:center}.group td{background:#d4dceb;font-weight:bold;font-size:11pt}.section td{background:#f3f5f9;font-weight:bold;text-align:center}</style>
</head>
<body>
    @include('filter-bnh-helper.word-cover')
    <h1>{{ $title }}</h1>
    <p><strong>Purok:</strong> {{ $filters['purok'] }} &nbsp;&nbsp; <strong>Kalagayan:</strong> {{ $filters['status'] }} &nbsp;&nbsp; <strong>Verdict:</strong> {{ $filters['verdict'] }}</p>
    @php
        $columns = '<th>NAME</th><th>KALAGAYAN</th><th>SALAYSAY / PAKIKIPAG-USAP</th><th>PAGHAHANDA</th><th>VERDICT</th>';
        $sections = $verdict === 'all'
            ? ['qualified' => 'Verdict = Qualified for BNH', 'not_qualified' => 'Verdict = Not Qualified for BNH']
            : [$verdict => null];
    @endphp
    <table>
        @if ($verdict !== 'all')
            <thead><tr>{!! $columns !!}</tr></thead>
        @endif
        <tbody>
            @forelse ($groups as $group)
                <tr class="group"><td colspan="5">{{ $group['label'] }}</td></tr>
                @foreach ($sections as $key => $heading)
                    @continue ($group[$key] === [])
                    @if ($heading !== null)
                        <tr class="section"><td colspan="5">{{ $heading }}</td></tr>
                        <tr>{!! $columns !!}</tr>
                    @endif
                    @foreach ($group[$key] as $row)
                        <tr>
                            <td>{{ $row['name'] }}</td>
                            <td>{{ $row['status'] }}</td>
                            <td class="center"><span style="font-family: Wingdings">{{ $row['is_valid1'] ? 'ü' : '' }}</span></td>
                            <td class="center"><span style="font-family: Wingdings">{{ $row['is_valid2'] ? 'ü' : '' }}</span></td>
                            <td class="center"><span style="font-family: Wingdings">{{ $row['qualified'] ? 'ü' : '' }}</span></td>
                        </tr>
                    @endforeach
                @endforeach
            @empty
                <tr><td colspan="5">No matching active records.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
