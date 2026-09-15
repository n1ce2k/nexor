<script setup>
import { NHtmlInput, NInput, NToggle } from '../core.js';

const props = defineProps({ data: { type: Object, required: true } });

props.data.items ??= [];
props.data.openFirst ??= true;

function addItem() {
    props.data.items.push({ title: '', content: '' });
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
        <NToggle v-model="data.openFirst" label="Первый пункт раскрыт" />

        <div v-for="(item, index) in data.items" :key="index"
             class="space-y-2 rounded-lg border border-[var(--surface-border)] p-3">
            <div class="flex items-center gap-2">
                <span class="w-6 text-center text-xs text-[var(--text-faint)]">{{ index + 1 }}</span>
                <NInput v-model="item.title" placeholder="Заголовок пункта (вопрос)" class="flex-1" />

                <button type="button" title="Выше" :disabled="index === 0"
                        class="rounded p-1 text-[var(--text-muted)] hover:text-[var(--text-strong)] disabled:opacity-30" @click="move(index, -1)">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m18 15-6-6-6 6" /></svg>
                </button>
                <button type="button" title="Ниже" :disabled="index === data.items.length - 1"
                        class="rounded p-1 text-[var(--text-muted)] hover:text-[var(--text-strong)] disabled:opacity-30" @click="move(index, 1)">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6" /></svg>
                </button>
                <button type="button" title="Удалить пункт"
                        class="rounded p-1 text-[var(--text-muted)] hover:text-red-600" @click="data.items.splice(index, 1)">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12" /></svg>
                </button>
            </div>

            <NHtmlInput v-model="item.content" rows="7rem" placeholder="Содержимое пункта (ответ)" />
        </div>

        <button type="button" class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-400" @click="addItem">
            + Добавить пункт
        </button>
    </div>
</template>
