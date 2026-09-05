@extends('nexor::admin.layouts.app')

@section('title', 'Рабочий стол')

@section('content')
    <x-nexor::admin.page-header title="Рабочий стол"
                         :description="'Здравствуйте, '.auth()->user()->name.'. Ниже — сводка по проекту.'" />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            @php $tag = $stat['url'] ? 'a' : 'div'; @endphp

            <{{ $tag }} @if ($stat['url']) href="{{ $stat['url'] }}" @endif
                class="surface flex items-center gap-4 rounded-[var(--radius-card)] border p-5 shadow-sm transition {{ $stat['url'] ? 'hover:border-brand-400 hover:shadow-md' : '' }}">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                    <x-nexor::admin.icon :name="$stat['icon']" class="size-5" />
                </span>

                <div class="min-w-0">
                    <p class="text-2xl font-semibold text-[var(--text-strong)]">{{ number_format($stat['value'], 0, ',', ' ') }}</p>
                    <p class="truncate text-xs text-[var(--text-muted)]">{{ $stat['label'] }}</p>
                </div>
            </{{ $tag }}>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-nexor::admin.card title="Инфоблоки" description="Быстрый переход к наполнению контентом" class="lg:col-span-2" :padding="false">
            @if ($iblocks->isEmpty())
                <x-nexor::admin.empty-state icon="layers"
                                     title="Инфоблоков пока нет"
                                     description="Создайте первый инфоблок, чтобы начать наполнять сайт контентом.">
                    @can('iblocks.create')
                        <x-nexor::admin.button :href="route('admin.iblocks.create')" icon="plus">Создать инфоблок</x-nexor::admin.button>
                    @endcan
                </x-nexor::admin.empty-state>
            @else
                <ul class="divide-y divide-[var(--surface-border)]">
                    @foreach ($iblocks as $iblock)
                        <li>
                            <a href="{{ route('admin.iblocks.elements.index', $iblock) }}"
                               class="flex items-center gap-4 px-5 py-3.5 transition hover:bg-[var(--surface-muted)]">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-[var(--surface-muted)] text-[var(--text-muted)]">
                                    <x-nexor::admin.icon name="folder" class="size-4.5" />
                                </span>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-[var(--text-strong)]">{{ $iblock->name }}</p>
                                    <p class="truncate text-xs text-[var(--text-muted)]">
                                        {{ $iblock->type?->name }} &middot; код <code class="font-mono">{{ $iblock->code }}</code>
                                    </p>
                                </div>

                                <x-nexor::admin.badge color="gray">{{ $iblock->elements_count }}</x-nexor::admin.badge>
                                <x-nexor::admin.icon name="chevron-right" class="size-4 shrink-0 text-[var(--text-faint)]" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-nexor::admin.card>

        <x-nexor::admin.card title="Последние действия" :padding="false">
            @if ($recent->isEmpty())
                <x-nexor::admin.empty-state icon="clock" title="Записей нет"
                                     description="Здесь появятся действия пользователей в админке." />
            @else
                <ul class="divide-y divide-[var(--surface-border)]">
                    @foreach ($recent as $entry)
                        <li class="px-5 py-3">
                            <p class="text-sm text-[var(--text-strong)]">
                                {{ $entry->actionLabel() }}
                                @if ($entry->description)
                                    <span class="text-[var(--text-muted)]">— {{ Str::limit($entry->description, 40) }}</span>
                                @endif
                            </p>
                            <p class="mt-0.5 text-xs text-[var(--text-faint)]">
                                {{ $entry->user?->name ?? 'Система' }} &middot; {{ $entry->created_at?->diffForHumans() }}
                            </p>
                        </li>
                    @endforeach
                </ul>

                @can('logs.view')
                    <div class="border-t border-[var(--surface-border)] px-5 py-3">
                        <x-nexor::admin.button :href="route('admin.logs.index')" variant="link" size="sm">
                            Весь журнал
                        </x-nexor::admin.button>
                    </div>
                @endcan
            @endif
        </x-nexor::admin.card>
    </div>
@endsection

