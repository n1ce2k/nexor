{{--
    Экран копии, поднятой не на своём домене.

    Приходит: $state (состояние лицензии) и $detailed — показывать подробности
    или нет. Подробности уходят только в панель: на публичной странице они
    ничего не решают.

    Разметка без сборки и без Vue: этот экран должен открываться и тогда, когда
    панель не собрана и лицензия не в порядке.
--}}
<!DOCTYPE html>
<html lang="ru" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $detailed ? 'Установка не активирована' : 'Сайт недоступен' }}</title>

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
            max-width: 34rem;
            width: 100%;
            padding: 3rem 2.5rem;
            border-radius: 1.25rem;
            background: #fff;
            box-shadow: 0 1px 3px rgb(15 23 42 / 8%), 0 12px 32px rgb(15 23 42 / 6%);
        }

        h1 { margin: 0 0 .75rem; font-size: 1.5rem; font-weight: 600; color: #0f172a; }
        p { margin: 0 0 1rem; color: #64748b; }
        p:last-child { margin-bottom: 0; }

        dl { margin: 1.5rem 0; display: grid; grid-template-columns: auto 1fr; gap: .5rem 1rem; font-size: .9375rem; }
        dt { color: #94a3b8; }
        dd { margin: 0; color: #0f172a; font-weight: 500; word-break: break-all; }

        code {
            display: block;
            margin-top: .5rem;
            padding: .75rem 1rem;
            border-radius: .625rem;
            background: #f1f5f9;
            color: #0f172a;
            font: 400 .8125rem/1.5 ui-monospace, SFMono-Regular, Menlo, monospace;
            word-break: break-all;
        }

        @media (prefers-color-scheme: dark) {
            body { background: #0b1120; color: #cbd5e1; }
            .card { background: #0f172a; box-shadow: none; }
            h1, dd { color: #f1f5f9; }
            p, dt { color: #94a3b8; }
            code { background: #1e293b; color: #e2e8f0; }
        }
    </style>
</head>
<body>
    <div class="card">
        @if ($detailed)
            <h1>Установка не активирована</h1>

            <p>{{ $state['message'] }}</p>

            <dl>
                <dt>Домен сайта</dt>
                <dd>{{ $state['current_host'] }}</dd>

                @if ($state['host'])
                    <dt>Домен ключа</dt>
                    <dd>{{ $state['host'] }}</dd>
                @endif

                @if ($state['number'])
                    <dt>Номер ключа</dt>
                    <dd>{{ $state['number'] }}</dd>
                @endif

                @if ($state['install'])
                    <dt>Установка</dt>
                    <dd>{{ $state['install'] }}</dd>
                @endif
            </dl>

            <p>
                Если сайт переехал на этот домен, введите ключ, выданный на него, и закрепите установку:
                <code>php artisan nexor:license nxr-...<br>php artisan nexor:license:bind</code>
            </p>

            <p>
                Снять проверку на время: <code>NEXOR_LICENSE_GUARD=off</code>
            </p>
        @else
            <h1>Сайт недоступен</h1>

            <p>Эта копия сайта не активирована. Обратитесь к администратору.</p>
        @endif
    </div>
</body>
</html>
