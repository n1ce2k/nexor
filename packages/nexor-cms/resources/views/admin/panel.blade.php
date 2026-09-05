<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="nexor-api" content="{{ url(\Nexor\Cms\Support\Nexor::routePrefix().'/api') }}">

    <title>{{ \Nexor\Cms\Models\Setting::get('site.name', config('app.name')) }} — панель управления</title>

    {{-- Applied before paint so a dark-theme reload never flashes white. --}}
    <script>
        (() => {
            const stored = localStorage.getItem('admin.theme');
            const dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', dark);
            document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
        })();
    </script>

    @vite(config('nexor.panel_assets'))

    {{-- Bundles registered here run before mount() and can extend the panel. --}}
    @if ($extensions = config('nexor.panel_extensions'))
        @vite($extensions)
    @endif
</head>
<body class="h-full font-sans antialiased">
    <div id="nexor-panel" data-base="{{ $base }}"></div>
</body>
</html>
