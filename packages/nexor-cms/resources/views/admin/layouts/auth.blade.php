<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', 'Вход') — {{ \Nexor\Cms\Models\Setting::get('site.name', config('app.name')) }}</title>

    <script>
        (() => {
            const stored = localStorage.getItem('admin.theme');
            const dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', dark);
            document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
        })();
    </script>

    @vite(config('nexor.assets'))
</head>
<body class="flex h-full items-center justify-center px-4 font-sans antialiased">
    <div class="w-full max-w-sm">
        <div class="mb-8 flex flex-col items-center gap-3">
            <span class="flex size-12 items-center justify-center rounded-2xl bg-brand-600 text-lg font-bold text-white">N</span>
            <div class="text-center">
                <h1 class="text-lg font-semibold text-[var(--text-strong)]">
                    {{ \Nexor\Cms\Models\Setting::get('site.name', config('app.name')) }}
                </h1>
                <p class="text-sm text-[var(--text-muted)]">Панель управления</p>
            </div>
        </div>

        @yield('content')
    </div>

    <x-nexor::admin.toasts />
</body>
</html>
