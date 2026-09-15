<script setup>
import { blockId, NHtmlInput, NInput } from '../core.js';

/**
 * Табы: вкладки меняются местами стрелками, открытая сразу — отмечается.
 */
const props = defineProps({ data: { type: Object, required: true } });

props.data.items ??= [];

if (!props.data.items.length) {
    props.data.items.push({ id: blockId(), title: '', content: '' });
}

props.data.items.forEach((item) => {
    item.id ||= blockId();
});

props.data.activeTabId ??= props.data.items[0]?.id ?? null;

function addItem() {
    props.data.items.push({ id: blockId(), title: '', content: '' });
}

function remove(index) {
    const [removed] = props.data.items.splice(index, 1);

    if (removed?.id === props.data.activeTabId) {
        props.data.activeTabId = props.data.items[0]?.id ?? null;
    }
}

function move(index, step) {
    const items = props.data.items;
    const target = index + step;

    if (target >= 0 && target < items.length) {
        items.splice(target, 0, items.splice(index, 1)[0]);
    }
}
</script>

<template>
    <div class="space-y-4">
        <div v-for="(item, index) in data.items" :key="item.id"
             class="space-y-2 rounded-lg border border-[var(--surface-border)] p-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="w-6 text-center text-xs text-[var(--text-faint)]">{{ index + 1 }}</span>
                <NInput v-model="item.title" placeholder="Название вкладки" class="min-w-48 flex-1" />

                <label class="flex cursor-pointer items-center gap-1.5 text-xs text-[var(--text-muted)]">
                    <input v-model="data.activeTabId" type="radio" :value="item.id" class="text-brand-600">
                    Открыта сразу
                </label>

                <button type="button" title="Левее" :disabled="index === 0"
                        class="rounded p-1 text-[var(--text-muted)] hover:text-[var(--text-strong)] disabled:opacity-30" @click="move(index, -1)">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6" /></svg>
                </button>
                <button type="button" title="Правее" :disabled="index === data.items.length - 1"
                        class="rounded p-1 text-[var(--text-muted)] hover:text-[var(--text-strong)] disabled:opacity-30" @click="move(index, 1)">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6" /></svg>
                </button>
                <button type="button" title="Удалить вкладку"
                        class="rounded p-1 text-[var(--text-muted)] hover:text-red-600" @click="remove(index)">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12" /></svg>
                </button>
            </div>

            <NHtmlInput v-model="item.content" rows="9rem" placeholder="Содержимое вкладки" />
        </div>

        <button type="button" class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-400" @click="addItem">
            + Добавить вкладку
        </button>
    </div>
</template>
