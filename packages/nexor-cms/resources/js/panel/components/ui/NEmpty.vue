<script setup>
import NIcon from './NIcon.vue';

defineProps({
    icon: { type: String, default: 'document' },
    title: { type: String, default: 'Пока пусто' },
    description: { type: String, default: null },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center px-6 py-14 text-center">
        <span class="flex size-12 items-center justify-center rounded-full bg-[var(--surface-muted)] text-[var(--text-faint)]">
            <NIcon :name="icon" size="size-6" />
        </span>

        <h3 class="mt-4 text-sm font-semibold text-[var(--text-strong)]">{{ title }}</h3>
        <p v-if="description" class="mt-1 max-w-sm text-sm text-[var(--text-muted)]">{{ description }}</p>

        <div v-if="$slots.default" class="mt-5 flex items-center gap-2">
            <slot />
        </div>
    </div>
</template>
