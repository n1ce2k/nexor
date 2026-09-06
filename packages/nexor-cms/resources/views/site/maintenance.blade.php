<!DOCTYPE html>
<html lang="ru" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $siteName }} — сайт на обслуживании</title>

    <style>
        :root { color-scheme: light dark; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: #f1f5f9;
            color: #334155;
            font: 400 16px/1.6 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .card {
            max-width: 32rem;
            width: 100%;
            padding: 3rem 2.5rem;
            border-radius: 1.25rem;
            background: #fff;
            box-shadow: 0 1px 3px rgb(15 23 42 / 8%), 0 12px 32px rgb(15 23 42 / 6%);
            text-align: center;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 3.5rem;
            height: 3.5rem;
            margin-bottom: 1.5rem;
            border-radius: 1rem;
            background: #1c47f5;
            color: #fff;
            font-size: 1.5rem;
            font-weight: 700;
        }

        h1 { margin: 0 0 .75rem; font-size: 1.5rem; font-weight: 600; color: #0f172a; }
        p { margin: 0; color: #64748b; }
        .site { margin-top: 2rem; font-size: .8125rem; color: #94a3b8; }

        @media (prefers-color-scheme: dark) {
            body { background: #0b1120; color: #cbd5e1; }
            .card { background: #0f172a; box-shadow: none; }
            h1 { color: #f1f5f9; }
            p { color: #94a3b8; }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">{{ mb_substr($siteName, 0, 1) }}</div>

        <h1>Скоро вернёмся</h1>

        <p>{{ $message ?: 'Сайт временно закрыт на технические работы. Загляните чуть позже — мы уже почти закончили.' }}</p>

        <p class="site">{{ $siteName }}</p>
    </div>
</body>
</html>
