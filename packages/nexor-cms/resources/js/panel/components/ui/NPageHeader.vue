<script setup>
import NIcon from './NIcon.vue';

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: null },
    back: { type: [String, Object], default: null },
    breadcrumbs: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <nav v-if="breadcrumbs.length" class="mb-2 flex items-center gap-1.5 text-xs text-[var(--text-muted)]">
                <template v-for="(crumb, index) in breadcrumbs" :key="index">
                    <NIcon v-if="index > 0" name="chevron-right" size="size-3 opacity-50" />
                    <router-link v-if="crumb.to" :to="crumb.to" class="truncate transition hover:text-[var(--text-strong)]">
                        {{ crumb.label }}
                    </router-link>
                    <span v-else class="truncate text-[var(--text-base)]">{{ crumb.label }}</span>
                </template>
            </nav>

            <div class="flex items-center gap-3">
                <router-link v-if="back" :to="back" title="Назад"
                             class="flex size-8 shrink-0 items-center justify-center rounded-lg border border-[var(--surface-border-strong)] text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                    <NIcon name="chevron-left" size="size-4" />
                </router-link>

                <h1 class="truncate text-xl font-semibold text-[var(--text-strong)]">{{ title }}</h1>
            </div>

            <p v-if="description" class="mt-1.5 text-sm text-[var(--text-muted)]">{{ description }}</p>
        </div>

        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
