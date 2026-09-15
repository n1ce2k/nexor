<script setup>
import { NField, NHtmlInput, NInput, NToggle } from '../core.js';
import MediaInput from '../components/MediaInput.vue';
import Segmented from '../components/Segmented.vue';

/**
 * Ссылки / Файлы: карточки со ссылкой или загруженным документом.
 */
const props = defineProps({
    data: { type: Object, required: true },
    iblock: { type: Object, required: true },
});

props.data.variant ??= 'apps';
props.data.cols = Number(props.data.cols ?? 3);
props.data.items ??= [];

const variants = [
    { value: 'apps', label: 'Текст и картинка' },
    { value: 'docs', label: 'Документы' },
];

const columns = [1, 2, 3, 4].map((count) => ({ value: count, label: String(count) }));

function addItem() {
    props.data.items.push({ text: '', link: '', file: null, image: null, isDownload: false });
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
        <div class="flex flex-wrap items-center gap-3">
            <Segmented v-model="data.variant" :options="variants" />
            <span class="text-sm text-[var(--text-muted)]">Колонок:</span>
            <Segmented v-model="data.cols" :options="columns" />
        </div>

        <NField label="Описание над карточками">
            <NHtmlInput v-model="data.description" rows="6rem" />
        </NField>

        <div v-for="(item, index) in data.items" :key="index"
             class="space-y-3 rounded-lg border border-[var(--surface-border)] p-3">
            <div class="flex items-center gap-2">
                <span class="w-6 text-center text-xs text-[var(--text-faint)]">{{ index + 1 }}</span>
                <NInput v-model="item.text" :placeholder="data.variant === 'docs' ? 'Название документа' : 'Текст карточки'" class="flex-1" />

                <button type="button" title="Выше" :disabled="index === 0"
                        class="rounded p-1 text-[var(--text-muted)] hover:text-[var(--text-strong)] disabled:opacity-30" @click="move(index, -1)">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m18 15-6-6-6 6" /></svg>
                </button>
                <button type="button" title="Ниже" :disabled="index === data.items.length - 1"
                        class="rounded p-1 text-[var(--text-muted)] hover:text-[var(--text-strong)] disabled:opacity-30" @click="move(index, 1)">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6" /></svg>
                </button>
                <button type="button" title="Удалить карточку"
                        class="rounded p-1 text-[var(--text-muted)] hover:text-red-600" @click="data.items.splice(index, 1)">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12" /></svg>
                </button>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <NField label="Ссылка" :hint="item.file ? 'Загружен файл — карточка ведёт на него.' : 'https://…, mailto:, tel: или /страница на сайте.'">
                    <NInput v-model="item.link" :disabled="Boolean(item.file)" placeholder="https://" />
                </NField>

                <NField v-if="data.variant === 'docs'" label="Или файл">
                    <MediaInput v-model="item.file" :iblock="iblock" kind="document" :with-alt="false" size="size-16" />
                </NField>

                <NField v-else label="Картинка">
                    <MediaInput v-model="item.image" :iblock="iblock" size="size-16" />
                </NField>
            </div>

            <NToggle v-if="data.variant === 'docs'" v-model="item.isDownload" label="Скачивать файл, а не открывать" />
        </div>

        <button type="button" class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-400" @click="addItem">
            + Добавить карточку
        </button>
    </div>
</template>
