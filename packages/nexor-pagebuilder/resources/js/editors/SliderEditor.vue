<script setup>
import { ref } from 'vue';
import { api, Draggable, NField, NInput, NToggle, useUi } from '../core.js';

/**
 * Слайдер: картинки, сколько видно, отступ, автопрокрутка, стрелки, точки.
 * Карусель на сайте — Swiper, который подключает сам сайт.
 */
const props = defineProps({
    data: { type: Object, required: true },
    iblock: { type: Object, required: true },
});

const ui = useUi();
const input = ref(null);
const uploading = ref(0);

props.data.images ??= [];
props.data.slidesPerView = Number(props.data.slidesPerView ?? 3);
props.data.gap = Number(props.data.gap ?? 20);
props.data.autoplay ??= false;
props.data.arrows ??= true;
props.data.dots ??= true;

async function onPick(event) {
    const files = [...event.target.files];

    event.target.value = '';

    for (const file of files) {
        uploading.value++;

        try {
            const uploaded = await api.post('pagebuilder/uploads', { iblock: props.iblock.id, kind: 'image', file }, true);

            props.data.images.push({ path: uploaded.path, src: uploaded.src, alt: '', title: '' });
        } catch (error) {
            ui.notifyError(error);
        } finally {
            uploading.value--;
        }
    }
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-end gap-4 rounded-lg bg-[var(--surface-muted)] p-3">
            <NField label="Слайдов видно">
                <NInput v-model.number="data.slidesPerView" type="number" min="1" max="6" class="w-24" />
            </NField>
            <NField label="Отступ, px">
                <NInput v-model.number="data.gap" type="number" min="0" max="100" class="w-24" />
            </NField>
            <NToggle v-model="data.autoplay" label="Автопрокрутка" />
            <NToggle v-model="data.arrows" label="Стрелки" />
            <NToggle v-model="data.dots" label="Точки" />
        </div>

        <p class="text-xs text-[var(--text-muted)]">
            Карусель работает на Swiper, подключённом на сайте. Без него картинки выводятся сеткой.
        </p>

        <div class="flex items-center justify-between gap-3">
            <p class="text-sm text-[var(--text-muted)]">
                {{ data.images.length ? `Слайдов: ${data.images.length}. Порядок — перетаскиванием.` : 'Картинок пока нет. Можно выбрать сразу несколько.' }}
            </p>

            <button type="button" :disabled="uploading > 0"
                    class="rounded-lg border border-[var(--surface-border)] px-3 py-1.5 text-sm font-medium text-[var(--text-strong)] transition hover:border-brand-500 disabled:opacity-60"
                    @click="input.click()">
                {{ uploading ? `Загружаем… (${uploading})` : 'Добавить картинки' }}
            </button>
            <input ref="input" type="file" multiple accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" @change="onPick">
        </div>

        <Draggable v-if="data.images.length" v-model="data.images" item-key="path" :animation="150" ghost-class="opacity-40"
                   class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <template #item="{ element: image, index }">
                <div class="space-y-2 rounded-lg border border-[var(--surface-border)] p-2">
                    <div class="relative cursor-grab active:cursor-grabbing">
                        <img :src="image.src" alt="" class="aspect-square w-full rounded-md object-cover">
                        <button type="button" title="Убрать картинку"
                                class="absolute top-1.5 right-1.5 rounded-full bg-black/60 p-1 text-white hover:bg-red-600"
                                @click="data.images.splice(index, 1)">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6 6 18M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <NInput v-model="image.alt" placeholder="Описание (alt)" />
                    <NInput v-model="image.title" placeholder="Подпись (title)" />
                </div>
            </template>
        </Draggable>
    </div>
</template>
