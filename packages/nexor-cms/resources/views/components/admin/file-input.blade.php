@props(['name', 'value' => null, 'accept' => null, 'image' => true])

@php
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $hasError = $errors->has($errorKey);
    $url = $value ? Storage::disk('public')->url($value) : null;
@endphp

<div x-data="fileField(@js($image ? $url : null))" class="flex items-start gap-3">
    <template x-if="preview">
        <img :src="preview" alt=""
             class="size-20 shrink-0 rounded-lg border border-[var(--surface-border)] object-cover">
    </template>

    <template x-if="! preview">
        <span class="flex size-20 shrink-0 items-center justify-center rounded-lg border border-dashed border-[var(--surface-border-strong)] text-[var(--text-faint)]">
            <x-nexor::admin.icon :name="$image ? 'image' : 'document'" class="size-6" />
        </span>
    </template>

    <div class="min-w-0 flex-1 space-y-2">
        <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-[var(--surface-border-strong)] bg-[var(--surface-panel)] px-3 py-2 text-sm font-medium text-[var(--text-base)] transition hover:bg-[var(--surface-muted)] {{ $hasError ? 'border-red-500' : '' }}">
            <x-nexor::admin.icon name="upload" class="size-4" />
            Выбрать файл
            <input type="file"
                   name="{{ $name }}"
                   id="{{ $name }}"
                   x-ref="input"
                   @change="pick($event)"
                   @if ($accept) accept="{{ $accept }}" @endif
                   class="sr-only"
                   {{ $attributes }}>
        </label>

        <input type="hidden" name="{{ $name }}_remove" :value="removed ? 1 : 0">

        <p class="truncate text-xs text-[var(--text-muted)]"
           x-text="fileName || @js($value ? basename($value) : 'Файл не выбран')"></p>

        <template x-if="preview || fileName || @js((bool) $value)">
            <button type="button" @click="clear()"
                    class="text-xs font-medium text-red-600 transition hover:underline dark:text-red-400">
                Удалить файл
            </button>
        </template>
    </div>
</div>
