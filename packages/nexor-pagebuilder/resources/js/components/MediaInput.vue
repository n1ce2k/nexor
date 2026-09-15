<script setup>
import { ref } from 'vue';
import { api, NInput, useUi } from '../core.js';

/**
 * Один файл блока: загрузка, превью, alt и title.
 *
 * Значение — `{ path, src, alt, title }` или null. `src` нужен только для
 * превью, на сервере хранится `path`.
 */
const props = defineProps({
    modelValue: { type: Object, default: null },
    iblock: { type: Object, required: true },
    kind: { type: String, default: 'image' },
    withAlt: { type: Boolean, default: true },
    withTitle: { type: Boolean, default: false },
    size: { type: String, default: 'size-28' },
});

const emit = defineEmits(['update:modelValue']);

const ui = useUi();
const input = ref(null);
const uploading = ref(false);

const accept = {
    video: 'video/mp4,video/webm,video/ogg',
    document: '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.rtf,.txt,.csv,.zip,.rar,.7z',
}[props.kind] ?? 'image/jpeg,image/png,image/webp,image/gif';

const pickLabel = { video: 'Выбрать видео', document: 'Выбрать файл' }[props.kind] ?? 'Выбрать фото';

/** Имя загруженного документа — картинки у него нет. */
function fileName(path) {
    return String(path ?? '').split('/').pop();
}

async function onPick(event) {
    const [file] = event.target.files;

    event.target.value = '';

    if (!file) {
        return;
    }

    uploading.value = true;

    try {
        const data = await api.post('pagebuilder/uploads', { iblock: props.iblock.id, kind: props.kind, file }, true);

        emit('update:modelValue', {
            path: data.path,
            src: data.src,
            alt: props.modelValue?.alt ?? '',
            title: props.modelValue?.title ?? '',
        });
    } catch (error) {
        ui.notifyError(error);
    } finally {
        uploading.value = false;
    }
}

function update(key, value) {
    emit('update:modelValue', { ...props.modelValue, [key]: value });
}
</script>

<template>
    <div class="flex items-start gap-3">
        <button type="button"
                :class="[size, 'relative flex shrink-0 items-center justify-center overflow-hidden rounded-lg border border-dashed border-[var(--surface-border-strong)] bg-[var(--surface-muted)] text-center text-xs text-[var(--text-muted)] transition hover:border-brand-500']"
                :disabled="uploading" @click="input.click()">
            <template v-if="modelValue?.src">
                <video v-if="kind === 'video'" :src="modelValue.src" class="size-full object-cover" muted></video>
                <span v-else-if="kind === 'document'" class="px-1 font-mono text-[10px] break-all">{{ fileName(modelValue.path) }}</span>
                <img v-else :src="modelValue.src" alt="" class="size-full object-cover">
            </template>
            <span v-else class="px-1">{{ uploading ? 'Загрузка…' : pickLabel }}</span>
        </button>

        <div class="min-w-0 flex-1 space-y-2">
            <template v-if="modelValue">
                <NInput v-if="withAlt" :model-value="modelValue.alt ?? ''" placeholder="Описание (alt)"
                        @update:model-value="update('alt', $event)" />
                <NInput v-if="withTitle" :model-value="modelValue.title ?? ''" placeholder="Подпись (title)"
                        @update:model-value="update('title', $event)" />
            </template>

            <div class="flex gap-3 text-xs">
                <button type="button" class="font-medium text-brand-600 hover:underline dark:text-brand-400"
                        :disabled="uploading" @click="input.click()">
                    {{ modelValue ? 'Заменить' : 'Загрузить' }}
                </button>
                <button v-if="modelValue" type="button" class="text-red-600 hover:underline dark:text-red-400"
                        @click="emit('update:modelValue', null)">
                    Убрать
                </button>
            </div>
        </div>

        <input ref="input" type="file" :accept="accept" class="hidden" @change="onPick">
    </div>
</template>
