<script setup>
import { onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    title: { type: String, default: null },
    maxWidth: { type: String, default: 'max-w-lg' },
});

const emit = defineEmits(['update:modelValue']);

function close() {
    emit('update:modelValue', false);
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        close();
    }
}

watch(() => props.modelValue, (open) => {
    document.body.classList.toggle('overflow-hidden', open);
    open ? window.addEventListener('keydown', onKeydown) : window.removeEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.body.classList.remove('overflow-hidden');
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0">
            <div v-if="modelValue" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="close"></div>

                <div :class="['surface relative w-full rounded-2xl border shadow-2xl', maxWidth]">
                    <header v-if="title"
                            class="flex items-center justify-between gap-4 border-b border-[var(--surface-border)] px-5 py-4">
                        <h2 class="text-sm font-semibold text-[var(--text-strong)]">{{ title }}</h2>
                        <button type="button" class="rounded-lg p-1 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)]"
                                @click="close">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round">
                                <path d="M18 6 6 18M6 6l12 12" />
                            </svg>
                        </button>
                    </header>

                    <div class="px-5 py-4">
                        <slot />
                    </div>

                    <footer v-if="$slots.footer"
                            class="flex items-center justify-end gap-2 border-t border-[var(--surface-border)] px-5 py-4">
                        <slot name="footer" />
                    </footer>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
