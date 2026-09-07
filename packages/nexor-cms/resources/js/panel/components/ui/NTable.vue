<script setup>
import NIcon from './NIcon.vue';

/**
 * Data-driven table.
 *
 * Columns are plain objects, so a page describes its list once and any of them
 * can be swapped for a custom cell through the `cell-<key>` slot:
 *
 *   { key: 'name', label: 'Название', sortable: true, align: 'left', width: '20rem' }
 */
defineProps({
    columns: { type: Array, required: true },
    rows: { type: Array, default: () => [] },
    rowKey: { type: String, default: 'id' },
    sort: { type: String, default: null },
    direction: { type: String, default: 'asc' },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['sort']);

function toggleSort(column) {
    if (!column.sortable) {
        return;
    }

    emit('sort', column.key);
}
</script>

<template>
    <div class="relative overflow-x-auto">
        <div v-if="loading" class="absolute inset-0 z-10 flex items-center justify-center bg-[var(--surface-panel)]/70">
            <svg class="size-6 animate-spin text-brand-600" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
                <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v3a5 5 0 0 0-5 5H4Z" />
            </svg>
        </div>

        <table class="w-full min-w-full text-left text-sm">
            <thead class="table-head text-xs font-medium tracking-wide uppercase">
                <tr>
                    <th v-for="column in columns" :key="column.key" scope="col"
                        :style="column.width ? { width: column.width } : null"
                        :class="['px-4 py-3 font-medium whitespace-nowrap', `text-${column.align || 'left'}`]">
                        <button v-if="column.sortable" type="button"
                                class="inline-flex items-center gap-1 transition hover:text-[var(--text-strong)]"
                                :class="sort === column.key && 'text-[var(--text-strong)]'"
                                @click="toggleSort(column)">
                            {{ column.label }}
                            <NIcon v-if="sort === column.key"
                                   :name="direction === 'asc' ? 'chevron-up' : 'chevron-down'" size="size-3.5" />
                            <NIcon v-else name="chevron-down" size="size-3.5 opacity-30" />
                        </button>
                        <span v-else>{{ column.label }}</span>
                    </th>
                </tr>
            </thead>

            <tbody>
                <tr v-for="row in rows" :key="row[rowKey]" class="table-row group transition">
                    <td v-for="column in columns" :key="column.key"
                        :class="['px-4 py-3 align-middle', `text-${column.align || 'left'}`,
                                 column.muted ? 'text-[var(--text-muted)]' : 'text-[var(--text-base)]']">
                        <slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">
                            {{ row[column.key] }}
                        </slot>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
