@php
    use Nexor\Cms\Models\Setting;
    use Nexor\Cms\Support\Site;

    $siteName = Setting::get('site.name', config('app.name'));
    $menu = Site::menu();
    $logo = Setting::get('site.logo');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', Setting::get('seo.meta_title') ?: $siteName)</title>
    <meta name="description" content="@yield('description', Setting::get('seo.meta_description'))">
    @hasSection('keywords')
        <meta name="keywords" content="@yield('keywords')">
    @endif

    @if ($favicon = Setting::get('site.favicon'))
        <link rel="icon" href="{{ Storage::disk('public')->url($favicon) }}">
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col font-sans">
    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-6xl items-center gap-6 px-4 sm:px-6 lg:px-8"
             x-data="{ open: false }">
            <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5">
                @if ($logo)
                    <img src="{{ Storage::disk('public')->url($logo) }}" alt="{{ $siteName }}" class="h-8 w-auto">
                @else
                    <span class="flex size-9 items-center justify-center rounded-xl bg-brand-600 text-sm font-bold text-white">N</span>
                @endif
                <span class="text-base font-semibold text-slate-900">{{ $siteName }}</span>
            </a>

            <nav class="hidden flex-1 items-center gap-6 md:flex">
                @foreach ($menu as $item)
                    <a href="{{ route('page', $item->code) }}"
                       class="text-sm font-medium text-slate-600 transition hover:text-brand-600 {{ request()->is($item->code) ? 'text-brand-600' : '' }}">
                        {{ $item->name }}
                    </a>
                @endforeach
            </nav>

            <div class="ml-auto hidden items-center gap-4 md:flex">
                @if ($phone = Setting::get('contacts.phone'))
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}"
                       class="text-sm font-semibold text-slate-900 hover:text-brand-600">{{ $phone }}</a>
                @endif
            </div>

            <button type="button" @click="open = ! open"
                    class="ml-auto rounded-lg p-2 text-slate-600 transition hover:bg-slate-100 md:hidden">
                <span class="sr-only">Меню</span>
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round">
                    <path d="M3 12h18M3 6h18M3 18h18" />
                </svg>
            </button>

            <div x-show="open" x-cloak @click.outside="open = false"
                 class="absolute inset-x-0 top-16 border-b border-slate-200 bg-white p-4 shadow-lg md:hidden">
                <nav class="flex flex-col gap-1">
                    @foreach ($menu as $item)
                        <a href="{{ route('page', $item->code) }}"
                           class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            {{ $item->name }}
                        </a>
                    @endforeach
                </nav>
            </div>
        </div>
    </header>

    <main class="flex-1">
        @yield('content')
    </main>

    <footer class="mt-20 border-t border-slate-200 bg-slate-50">
        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:px-6 md:grid-cols-3 lg:px-8">
            <div>
                <p class="text-base font-semibold text-slate-900">{{ $siteName }}</p>
                @if ($tagline = Setting::get('site.tagline'))
                    <p class="mt-2 text-sm text-slate-600">{{ $tagline }}</p>
                @endif
            </div>

            <div>
                <p class="mb-3 text-sm font-semibold text-slate-900">Разделы</p>
                <ul class="space-y-2">
                    @foreach ($menu as $item)
                        <li>
                            <a href="{{ route('page', $item->code) }}"
                               class="text-sm text-slate-600 transition hover:text-brand-600">{{ $item->name }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div>
                <p class="mb-3 text-sm font-semibold text-slate-900">Контакты</p>
                <ul class="space-y-2 text-sm text-slate-600">
                    @if ($phone = Setting::get('contacts.phone'))
                        <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="hover:text-brand-600">{{ $phone }}</a></li>
                    @endif
                    @if ($email = Setting::get('contacts.email'))
                        <li><a href="mailto:{{ $email }}" class="hover:text-brand-600">{{ $email }}</a></li>
                    @endif
                    @if ($address = Setting::get('contacts.address'))
                        <li>{{ $address }}</li>
                    @endif
                    @if ($hours = Setting::get('contacts.work_hours'))
                        <li>{{ $hours }}</li>
                    @endif
                </ul>
            </div>
        </div>

        <div class="border-t border-slate-200">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-5 text-xs text-slate-500 sm:px-6 lg:px-8">
                <p>&copy; {{ date('Y') }} {{ $siteName }}</p>
                <a href="{{ route('admin.dashboard') }}" class="transition hover:text-brand-600">Панель управления</a>
            </div>
        </div>
    </footer>

{{--    {!! Setting::get('seo.counters') !!}--}}
</body>
</html>
