{{--
    Экран неподготовленной модели пользователя.

    Приходит: $model (класс), $missing (чего не хватает) и $snippet (что
    дописать). Разметка без сборки и без Vue: до панели дело ещё не дошло.
--}}
<!DOCTYPE html>
<html lang="ru" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Модель пользователя не подготовлена</title>

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
            max-width: 42rem;
            width: 100%;
            padding: 3rem 2.5rem;
            border-radius: 1.25rem;
            background: #fff;
            box-shadow: 0 1px 3px rgb(15 23 42 / 8%), 0 12px 32px rgb(15 23 42 / 6%);
        }

        h1 { margin: 0 0 .75rem; font-size: 1.5rem; font-weight: 600; color: #0f172a; }
        h2 { margin: 1.75rem 0 .5rem; font-size: .9375rem; font-weight: 600; color: #0f172a; }
        p { margin: 0 0 1rem; color: #64748b; }
        p:last-child { margin-bottom: 0; }

        ul { margin: 0 0 1rem; padding-left: 1.25rem; color: #64748b; }
        li { margin-bottom: .25rem; }

        pre, code {
            font: 400 .8125rem/1.6 ui-monospace, SFMono-Regular, Menlo, monospace;
        }

        pre {
            margin: 0 0 1rem;
            padding: 1rem 1.25rem;
            border-radius: .625rem;
            background: #f1f5f9;
            color: #0f172a;
            overflow-x: auto;
        }

        @media (prefers-color-scheme: dark) {
            body { background: #0b1120; color: #cbd5e1; }
            .card { background: #0f172a; box-shadow: none; }
            h1, h2 { color: #f1f5f9; }
            p, ul { color: #94a3b8; }
            pre { background: #1e293b; color: #e2e8f0; }
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Модель пользователя не подготовлена</h1>

        <p>
            Панели нужны от <code>{{ $model }}</code> контракт, трейты ролей и своих полей,
            колонки CMS в списке заполняемых и их приведения типов. Сейчас не хватает:
        </p>

        <ul>
            @foreach ($missing as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>

        <h2>Дописать автоматически</h2>

        <pre>php artisan nexor:user-model</pre>

        <h2>Или руками</h2>

        <pre>{{ $snippet }}</pre>

        <p>Если кеши собраны, после правки сбросьте их: <code>php artisan optimize:clear</code></p>
    </div>
</body>
</html>
