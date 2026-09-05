<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Панель управления') — {{ \Nexor\Cms\Models\Setting::get('site.name', config('app.name')) }}</title>

    {{-- Applied before paint so a dark-theme reload never flashes white. --}}
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
<body class="h-full font-sans antialiased">
    <div class="flex min-h-full" x-data>
        @include('nexor::admin.partials.sidebar')

        <div class="flex min-w-0 flex-1 flex-col lg:pl-[var(--sidebar-width)]"
             :style="`--sidebar-width: ${$store.sidebar.collapsed ? '4.5rem' : '16rem'}`">
            @include('nexor::admin.partials.topbar')

            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                <div class="mx-auto w-full max-w-7xl">
                    @yield('content')
                </div>
            </main>

            <footer class="px-4 py-5 text-center text-xs text-[var(--text-faint)] sm:px-6 lg:px-8">
                {{ \Nexor\Cms\Models\Setting::get('site.name', config('app.name')) }} — панель управления
                &middot; Laravel {{ app()->version() }}
                <br>
                created by N1ce
            </footer>
        </div>
    </div>

    <x-nexor::admin.toasts />

    @stack('scripts')
</body>
</html>
