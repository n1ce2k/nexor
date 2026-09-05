<script setup>
import { computed } from 'vue';
import NIcon from './NIcon.vue';

const props = defineProps({
    meta: { type: Object, default: null },
});

const emit = defineEmits(['change']);

const pages = computed(() => {
    if (!props.meta) {
        return [];
    }

    const { current_page: current, last_page: last } = props.meta;
    const result = [];

    for (let page = 1; page <= last; page++) {
        const nearEdge = page === 1 || page === last;
        const nearCurrent = Math.abs(page - current) <= 1;

        if (nearEdge || nearCurrent) {
            result.push(page);
        } else if (result[result.length - 1] !== '…') {
            result.push('…');
        }
    }

    return result;
});

const from = computed(() => props.meta?.from ?? 0);
const to = computed(() => props.meta?.to ?? 0);
</script>

<template>
    <div v-if="meta && meta.total > 0"
         class="flex flex-wrap items-center justify-between gap-3 border-t border-[var(--surface-border)] px-4 py-3">
        <p class="text-xs text-[var(--text-muted)]">
            Показано {{ from }}–{{ to }} из {{ meta.total }}
        </p>

        <nav v-if="meta.last_page > 1" class="flex items-center gap-1">
            <button type="button" class="flex size-8 items-center justify-center rounded-lg text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] disabled:opacity-40 disabled:hover:bg-transparent"
                    :disabled="meta.current_page === 1"
                    @click="emit('change', meta.current_page - 1)">
                <NIcon name="chevron-left" size="size-4" />
            </button>

            <template v-for="(page, index) in pages" :key="index">
                <span v-if="page === '…'" class="px-2 text-[var(--text-faint)]">…</span>
                <button v-else type="button"
                        class="flex size-8 items-center justify-center rounded-lg text-xs font-medium transition"
                        :class="page === meta.current_page
                            ? 'bg-brand-600 font-semibold text-white'
                            : 'text-[var(--text-muted)] hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]'"
                        @click="emit('change', page)">
                    {{ page }}
                </button>
            </template>

            <button type="button" class="flex size-8 items-center justify-center rounded-lg text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] disabled:opacity-40 disabled:hover:bg-transparent"
                    :disabled="meta.current_page === meta.last_page"
                    @click="emit('change', meta.current_page + 1)">
                <NIcon name="chevron-right" size="size-4" />
            </button>
        </nav>
    </div>
</template>
