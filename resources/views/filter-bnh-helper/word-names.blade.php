<!doctype html>
<html>
<head><meta charset="utf-8"><title>{{ $title }}</title>
<style>@page{margin:.55in}body{font-family:Arial,sans-serif;font-size:11pt}h1{text-align:center;font-size:16pt}p{margin:2px 0}.filters{margin:4px 0 14px}h2{font-size:13pt;margin:16px 0 4px;padding-bottom:2px;border-bottom:1px solid #222}h3{font-size:11pt;margin:8px 0 3px}.name{margin-left:.3in}.none{margin-left:.3in;color:#555;font-style:italic}</style>
</head>
<body>
    @include('filter-bnh-helper.word-cover')
    <h1>{{ $title }}</h1>
    <p class="filters"><strong>Purok:</strong> {{ $filters['purok'] }} &nbsp;&nbsp; <strong>Kalagayan:</strong> {{ $filters['status'] }}</p>
    @php
        $lists = array_filter([
            'qualified' => $showQualified ? 'Kwalipikado sa Pagtanggap ng BNH' : null,
            'not_qualified' => $showMinuses ? 'Hindi Kwalipikado sa Pagtanggap ng BNH' : null,
        ]);
    @endphp
    @forelse ($groups as $group)
        <h2>{{ $group['label'] }}</h2>
        @foreach ($lists as $key => $heading)
            <h3>{{ $heading }}</h3>
            @forelse ($group[$key] as $name)
                <p class="name">{{ $loop->iteration }}. {{ $name }}</p>
            @empty
                <p class="none">Wala</p>
            @endforelse
        @endforeach
    @empty
        <p>No matching active records.</p>
    @endforelse
</body>
</html>
