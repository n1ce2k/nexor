<div class="pointer-events-none fixed top-4 right-4 z-[80] flex w-full max-w-sm flex-col gap-2">
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-x-4"
             x-transition:enter-end="opacity-100 translate-x-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="surface pointer-events-auto flex items-start gap-3 rounded-xl border p-3.5 shadow-lg"
             :class="{
                 'border-l-4 border-l-emerald-500': toast.type === 'success',
                 'border-l-4 border-l-red-500': toast.type === 'error',
                 'border-l-4 border-l-amber-500': toast.type === 'warning',
                 'border-l-4 border-l-brand-500': toast.type === 'info',
             }">
            <p class="flex-1 text-sm text-[var(--text-base)]" x-text="toast.message"></p>

            <button type="button" @click="$store.toasts.dismiss(toast.id)"
                    class="shrink-0 rounded p-0.5 text-[var(--text-faint)] transition hover:text-[var(--text-strong)]">
                <x-nexor::admin.icon name="x" class="size-3.5" />
            </button>
        </div>
    </template>
</div>

@php
    $flashes = array_filter([
        'success' => session('success'),
        'error' => session('error'),
        'warning' => session('warning'),
        'info' => session('status'),
    ]);
@endphp

@if ($flashes)
    <script>
        document.addEventListener('alpine:init', () => {
            @foreach ($flashes as $type => $message)
                Alpine.store('toasts').push(@js($message), @js($type));
            @endforeach
        });
    </script>
@endif
