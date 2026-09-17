@php
    $user = auth()->user();
@endphp

<header class="surface sticky top-0 z-30 flex h-16 shrink-0 items-center gap-3 border-b px-4 sm:px-6 lg:px-8">
    <button type="button" @click="$store.sidebar.mobileOpen = true"
            class="-ml-1 rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] lg:hidden">
        <x-nexor::admin.icon name="menu" class="size-5" />
    </button>

    <div class="min-w-0 flex-1">
        @hasSection('topbar')
            @yield('topbar')
        @endif
    </div>

    <a href="{{ url('/') }}" target="_blank" rel="noopener"
       class="hidden items-center gap-2 rounded-lg px-3 py-2 text-sm text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)] sm:flex">
        <x-nexor::admin.icon name="eye" class="size-4" />
        Сайт
    </a>

    <button type="button" @click="$store.theme.toggle()"
            class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]"
            title="Сменить тему">
        <x-nexor::admin.icon name="sun" class="size-5" x-show="$store.theme.current === 'dark'" x-cloak />
        <x-nexor::admin.icon name="moon" class="size-5" x-show="$store.theme.current !== 'dark'" x-cloak />
    </button>

    <x-nexor::admin.dropdown width="w-56">
        <x-slot:trigger>
            <button type="button" class="flex items-center gap-2.5 rounded-lg p-1 pr-2 transition hover:bg-[var(--surface-muted)]">
                @if ($user->avatar_url)
                    <img src="{{ $user->avatar_url }}" alt="" class="size-8 rounded-full object-cover">
                @else
                    <span class="flex size-8 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">
                        {{ \Nexor\Cms\Support\Nexor::initials($user->name) }}
                    </span>
                @endif

                <span class="hidden max-w-32 truncate text-sm font-medium text-[var(--text-strong)] sm:block">
                    {{ $user->name }}
                </span>

                <x-nexor::admin.icon name="chevron-down" class="size-3.5 text-[var(--text-faint)]" />
            </button>
        </x-slot:trigger>

        <div class="border-b border-[var(--surface-border)] px-3 py-2">
            <p class="truncate text-sm font-medium text-[var(--text-strong)]">{{ $user->name }}</p>
            <p class="truncate text-xs text-[var(--text-muted)]">{{ $user->email }}</p>
            @if ($user->roles->isNotEmpty())
                <p class="mt-1 truncate text-xs text-[var(--text-faint)]">{{ $user->roles->pluck('name')->join(', ') }}</p>
            @endif
        </div>

        <div class="pt-1">
            <x-nexor::admin.dropdown-item :href="route('admin.profile.edit')" icon="user">Мой профиль</x-nexor::admin.dropdown-item>

            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <x-nexor::admin.dropdown-item type="submit" icon="logout" danger>Выйти</x-nexor::admin.dropdown-item>
            </form>
        </div>
    </x-nexor::admin.dropdown>
</header>
