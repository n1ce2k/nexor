<script setup>
defineProps({
    title: { type: String, default: null },
    description: { type: String, default: null },
    padding: { type: Boolean, default: true },
});
</script>

<template>
    <section class="surface rounded-[var(--radius-card)] border shadow-sm">
        <header v-if="title || $slots.actions"
                class="flex items-start justify-between gap-4 border-b border-[var(--surface-border)] px-5 py-4">
            <div class="min-w-0">
                <h2 v-if="title" class="text-sm font-semibold text-[var(--text-strong)]">{{ title }}</h2>
                <p v-if="description" class="mt-1 text-xs text-[var(--text-muted)]">{{ description }}</p>
            </div>
            <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
                <slot name="actions" />
            </div>
        </header>

        <div :class="padding ? 'p-5' : ''">
            <slot />
        </div>

        <footer v-if="$slots.footer"
                class="flex items-center justify-end gap-2 border-t border-[var(--surface-border)] px-5 py-4">
            <slot name="footer" />
        </footer>
    </section>
</template>
