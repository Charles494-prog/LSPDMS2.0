<!doctype html>
<html>
<head><meta charset="utf-8"><title>{{ $title }}</title>
<style>@page{margin:.55in}body{font-family:Arial,sans-serif;font-size:10pt}h1{text-align:center;font-size:16pt}p{margin:4px 0 12px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #222;padding:6px;text-align:left}th{background:#e8edf5}.number{text-align:center}.total{font-weight:bold;background:#f0f3f8}.purok-total{font-weight:bold;background:#f6f8fb}</style>
</head>
<body>
    @include('filter-bnh-helper.word-cover')
    <h1>{{ $title }}</h1>
    <p><strong>Purok:</strong> {{ $filters['purok'] }} &nbsp;&nbsp; <strong>Kalagayan:</strong> {{ $filters['status'] }}</p>
    <table>
        <thead>
            <tr>
                <th rowspan="2">PUROK-GRUPO</th>
                @if ($showQualified)<th colspan="3">QUALIFIED FOR BNH</th>@endif
                @if ($showMinuses)<th colspan="3">TOTAL MINUSES</th>@endif
            </tr>
            <tr>
                @foreach ([$showQualified, $showMinuses] as $showCounts)
                    @if ($showCounts)
                        <th class="number">L</th><th class="number">B</th><th class="number">TOTAL</th>
                    @endif
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr class="{{ $row['type'] === 'grand_total' ? 'total' : ($row['type'] === 'purok_total' ? 'purok-total' : '') }}">
                    <td>{{ $row['label'] }}</td>
                    @if ($showQualified)<td class="number">{{ $row['male'] }}</td><td class="number">{{ $row['female'] }}</td><td class="number">{{ $row['total'] }}</td>@endif
                    @if ($showMinuses)<td class="number">{{ $row['minuses_male'] }}</td><td class="number">{{ $row['minuses_female'] }}</td><td class="number">{{ $row['minuses_total'] }}</td>@endif
                </tr>
            @empty
                <tr><td colspan="{{ 1 + ($showQualified ? 3 : 0) + ($showMinuses ? 3 : 0) }}">No configured Purok groups found.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
