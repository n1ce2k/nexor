<script setup>
import { useUi } from '../../stores/ui';

const ui = useUi();

const accents = {
    success: 'border-l-4 border-l-emerald-500',
    error: 'border-l-4 border-l-red-500',
    warning: 'border-l-4 border-l-amber-500',
    info: 'border-l-4 border-l-brand-500',
};
</script>

<template>
    <div class="pointer-events-none fixed top-4 right-4 z-[80] flex w-full max-w-sm flex-col gap-2">
        <TransitionGroup
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-x-4 opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0">
            <div v-for="toast in ui.toasts" :key="toast.id"
                 :class="['surface pointer-events-auto flex items-start gap-3 rounded-xl border p-3.5 shadow-lg',
                          accents[toast.type] || accents.info]">
                <p class="flex-1 text-sm text-[var(--text-base)]">{{ toast.message }}</p>

                <button type="button" class="shrink-0 rounded p-0.5 text-[var(--text-faint)] transition hover:text-[var(--text-strong)]"
                        @click="ui.dismiss(toast.id)">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
