<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'IT Recovery') · LSPDMS</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; font-size: 14px; background: #f4f4f5; color: #18181b; }
        main { max-width: 960px; margin: 0 auto; padding: 24px 16px 48px; }
        header { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        h1 { font-size: 20px; margin: 0; }
        h2 { font-size: 16px; margin: 0 0 8px; }
        .card { background: #fff; border: 1px solid #e4e4e7; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
        .warning { border-color: #f59e0b; background: #fffbeb; }
        .status { border-color: #16a34a; background: #f0fdf4; }
        .errors { border-color: #dc2626; background: #fef2f2; color: #991b1b; }
        label { display: block; font-weight: 600; margin: 8px 0 4px; }
        input[type=email], input[type=password], input[type=text], select { width: 100%; max-width: 420px; padding: 8px; border: 1px solid #a1a1aa; border-radius: 6px; font: inherit; }
        button { margin-top: 10px; padding: 8px 14px; border: 0; border-radius: 6px; background: #18181b; color: #fff; font: inherit; cursor: pointer; }
        button.link { background: none; color: #1d4ed8; padding: 0; margin: 0; text-decoration: underline; }
        a { color: #1d4ed8; }
        nav { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #e4e4e7; vertical-align: top; }
        code.secret { display: inline-block; font-size: 18px; padding: 8px 12px; background: #18181b; color: #fff; border-radius: 6px; user-select: all; word-break: break-all; }
        .muted { color: #71717a; }
    </style>
</head>
<body>
<main>
    @if (session('status'))
        <div class="card status" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="card errors" role="alert">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif
    @yield('content')
</main>
</body>
</html>
