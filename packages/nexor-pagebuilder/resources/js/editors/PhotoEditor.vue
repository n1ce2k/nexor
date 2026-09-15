<script setup>
import { computed, ref } from 'vue';
import { api, Draggable, NField, NInput, NToggle, useUi } from '../core.js';
import Segmented from '../components/Segmented.vue';

/**
 * Фото / Галерея: готовые раскладки («2 в ряд», «Сетка 2×2», «Асимметрия»)
 * или своя сетка. Фото сверх видимых прячутся под «+N» на последнем.
 */
const props = defineProps({
    data: { type: Object, required: true },
    iblock: { type: Object, required: true },
});

const ui = useUi();
const input = ref(null);
const uploading = ref(0);

props.data.images ??= [];
props.data.layoutMode ??= 'preset';
props.data.presetId = String(props.data.presetId ?? '2');
props.data.asymmetricDir ??= 'top';
props.data.customCols = Number(props.data.customCols ?? 4);
props.data.customVisible = Number(props.data.customVisible ?? 4);
props.data.showOverlay ??= true;

const modes = [
    { value: 'preset', label: 'Готовая раскладка' },
    { value: 'custom', label: 'Своя сетка' },
];

const presets = [
    { value: '1', label: '1 фото' },
    { value: '2', label: '2 в ряд' },
    { value: '3', label: '3 в ряд' },
    { value: '4', label: '4 в ряд' },
    { value: '4_grid', label: 'Сетка 2×2' },
    { value: '3_asym', label: 'Асимметрия' },
];

const directions = [
    { value: 'top', label: 'Крупное сверху' },
    { value: 'bottom', label: 'Крупное снизу' },
    { value: 'left', label: 'Крупное слева' },
    { value: 'right', label: 'Крупное справа' },
];

const visible = computed(() => {
    if (props.data.layoutMode === 'custom') {
        return props.data.customVisible;
    }

    return { 1: 1, 2: 2, 3: 3, 4: 4, '4_grid': 4, '3_asym': 3 }[props.data.presetId] ?? 2;
});

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
        <div class="space-y-3 rounded-lg bg-[var(--surface-muted)] p-3">
            <Segmented v-model="data.layoutMode" :options="modes" />

            <template v-if="data.layoutMode === 'preset'">
                <Segmented v-model="data.presetId" :options="presets" />
                <Segmented v-if="data.presetId === '3_asym'" v-model="data.asymmetricDir" :options="directions" />
            </template>

            <div v-else class="flex flex-wrap gap-4">
                <NField label="Колонок">
                    <NInput v-model.number="data.customCols" type="number" min="1" max="12" class="w-24" />
                </NField>
                <NField label="Видно фото">
                    <NInput v-model.number="data.customVisible" type="number" min="1" max="100" class="w-24" />
                </NField>
            </div>

            <NToggle v-model="data.showOverlay"
                     label="Прятать лишние фото под «+N»"
                     :hint="`Видно ${visible}, остальные открываются в галерее.`" />
        </div>

        <div class="flex items-center justify-between gap-3">
            <p class="text-sm text-[var(--text-muted)]">
                {{ data.images.length ? `Фото: ${data.images.length}. Порядок — перетаскиванием.` : 'Фото пока нет. Можно выбрать сразу несколько.' }}
            </p>

            <button type="button" :disabled="uploading > 0"
                    class="rounded-lg border border-[var(--surface-border)] px-3 py-1.5 text-sm font-medium text-[var(--text-strong)] transition hover:border-brand-500 disabled:opacity-60"
                    @click="input.click()">
                {{ uploading ? `Загружаем… (${uploading})` : 'Добавить фото' }}
            </button>
            <input ref="input" type="file" multiple accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" @change="onPick">
        </div>

        <Draggable v-if="data.images.length" v-model="data.images" item-key="path" :animation="150" ghost-class="opacity-40"
                   class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <template #item="{ element: image, index }">
                <div :class="['space-y-2 rounded-lg border p-2', data.showOverlay && index >= visible ? 'border-dashed border-[var(--surface-border)] opacity-70' : 'border-[var(--surface-border)]']">
                    <div class="relative cursor-grab active:cursor-grabbing">
                        <img :src="image.src" alt="" class="aspect-square w-full rounded-md object-cover">
                        <span v-if="data.showOverlay && index >= visible"
                              class="absolute bottom-1.5 left-1.5 rounded bg-black/60 px-1.5 py-0.5 text-[10px] text-white">в галерее</span>
                        <button type="button" title="Убрать фото"
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
