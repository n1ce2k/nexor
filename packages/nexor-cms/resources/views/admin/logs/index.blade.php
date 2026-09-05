@extends('nexor::admin.layouts.app')

@section('title', 'Журнал действий')

@section('content')
    <x-nexor::admin.page-header title="Журнал действий"
                         description="Кто и что менял в панели управления." />

    <x-nexor::admin.card :padding="false">
        <div class="border-b border-[var(--surface-border)] p-4">
            <x-nexor::admin.filters placeholder="Описание...">
                <x-nexor::admin.select name="user" :selected="request('user')" placeholder="Все пользователи"
                                :options="$users->all()" class="w-auto" />

                <x-nexor::admin.select name="action" :selected="request('action')" placeholder="Все действия"
                                :options="$actions" class="w-auto" />
            </x-nexor::admin.filters>
        </div>

        @if ($logs->isEmpty())
            <x-nexor::admin.empty-state icon="clock" title="Записей нет"
                                 description="Действия пользователей появятся здесь автоматически." />
        @else
            <x-nexor::admin.table>
                <x-slot:head>
                    <x-nexor::admin.table.heading width="11rem">Дата</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading width="10rem">Пользователь</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading width="10rem">Действие</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading>Объект</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading width="10rem">IP</x-nexor::admin.table.heading>
                </x-slot:head>

                @foreach ($logs as $log)
                    <x-nexor::admin.table.row :id="$log->id" x-data="{ open: false }">
                        <x-nexor::admin.table.cell muted>
                            <span class="text-xs">{{ $log->created_at?->format('d.m.Y H:i:s') }}</span>
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell>
                            <span class="text-sm">{{ $log->user?->name ?? 'Система' }}</span>
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell>
                            <x-nexor::admin.badge :color="match ($log->action) {
                                'created' => 'green',
                                'updated' => 'blue',
                                'deleted', 'login_failed' => 'red',
                                'login' => 'violet',
                                default => 'gray',
                            }">{{ $log->actionLabel() }}</x-nexor::admin.badge>
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell>
                            <p class="text-sm text-[var(--text-strong)]">{{ $log->description ?? '—' }}</p>

                            @if ($log->changes)
                                <button type="button" @click="open = ! open"
                                        class="mt-0.5 text-xs text-brand-600 hover:underline dark:text-brand-400">
                                    <span x-text="open ? 'скрыть изменения' : 'показать изменения'"></span>
                                </button>

                                <div x-show="open" x-collapse x-cloak class="mt-2 space-y-1">
                                    @foreach ($log->changes as $field => $change)
                                        <p class="font-mono text-xs text-[var(--text-muted)]">
                                            <span class="text-[var(--text-base)]">{{ $field }}</span>:
                                            <span class="line-through">{{ Str::limit((string) ($change['from'] ?? ''), 40) }}</span>
                                            →
                                            <span class="text-emerald-600 dark:text-emerald-400">{{ Str::limit((string) ($change['to'] ?? ''), 40) }}</span>
                                        </p>
                                    @endforeach
                                </div>
                            @endif
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell muted>
                            <code class="font-mono text-xs">{{ $log->ip ?? '—' }}</code>
                        </x-nexor::admin.table.cell>
                    </x-nexor::admin.table.row>
                @endforeach
            </x-nexor::admin.table>

            <x-nexor::admin.pagination :paginator="$logs" />
        @endif
    </x-nexor::admin.card>
@endsection
