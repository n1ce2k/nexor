@props([
    'action',
    'title' => 'Удалить запись?',
    'message' => 'Действие нельзя отменить.',
    'label' => 'Удалить',
    'variant' => 'ghost',
    'size' => 'sm',
    'icon' => 'trash',
    'iconOnly' => false,
])

<div x-data="{ open: false }" class="inline-flex">
    <button type="button"
            @click="open = true"
            title="{{ $label }}"
            class="inline-flex items-center justify-center gap-1.5 rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-400">
        <x-nexor::admin.icon :name="$icon" class="size-4" />
        @unless ($iconOnly)
            <span class="text-xs font-medium">{{ $label }}</span>
        @endunless
    </button>

    <x-nexor::admin.modal :title="$title" max-width="max-w-md">
        <p class="text-sm text-[var(--text-base)]">{{ $message }}</p>

        <x-slot:footer>
            <x-nexor::admin.button variant="secondary" size="sm" @click="open = false">Отмена</x-nexor::admin.button>

            <form method="POST" action="{{ $action }}">
                @csrf
                @method('DELETE')
                <x-nexor::admin.button type="submit" variant="danger" size="sm">{{ $label }}</x-nexor::admin.button>
            </form>
        </x-slot:footer>
    </x-nexor::admin.modal>
</div>
