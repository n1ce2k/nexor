<script setup>
import { computed, ref } from 'vue';
import NIcon from '../ui/NIcon.vue';

/**
 * Картинка элемента: анонса или подробной страницы.
 *
 * Значение — небольшой объект, потому что форме нужно держать три состояния
 * сразу: что уже сохранено, что редактор выбрал сейчас и что он удалил.
 *
 *   { url: string|null, file: File|null, remove: boolean }
 *
 * Файл кладётся перетаскиванием или обычным выбором по клику — это одно и то
 * же поле `<input type="file">`, просто с двумя способами попасть в него.
 */
const props = defineProps({
    modelValue: { type: Object, default: () => ({ url: null, file: null, remove: false }) },
    accept: { type: String, default: 'image/*' },
    error: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const input = ref(null);
const dragging = ref(false);
const localUrl = ref(null);

const value = computed(() => ({
    url: props.modelValue?.url ?? null,
    file: props.modelValue?.file ?? null,
    remove: Boolean(props.modelValue?.remove),
}));

/** Что показывать: только что выбранный файл важнее сохранённого. */
const preview = computed(() => {
    if (value.value.file) {
        return localUrl.value;
    }

    return value.value.remove ? null : value.value.url;
});

const fileName = computed(() => value.value.file?.name ?? (value.value.url ? value.value.url.split('/').pop() : null));

function accepts(file) {
    return file && file.type.startsWith('image/');
}

function take(file) {
    if (!accepts(file)) {
        return;
    }

    if (localUrl.value) {
        URL.revokeObjectURL(localUrl.value);
    }

    localUrl.value = URL.createObjectURL(file);

    // Новая картинка снимает пометку об удалении: редактор передумал.
    emit('update:modelValue', { ...value.value, file, remove: false });
}

function pick(event) {
    take(event.target.files?.[0]);
    event.target.value = '';
}

function drop(event) {
    dragging.value = false;
    take(event.dataTransfer?.files?.[0]);
}

function clear() {
    if (localUrl.value) {
        URL.revokeObjectURL(localUrl.value);
        localUrl.value = null;
    }

    // Сохранённую картинку помечаем к удалению, невыбранную просто забываем.
    emit('update:modelValue', { url: value.value.url, file: null, remove: Boolean(value.value.url) });
}
</script>

<template>
    <div>
        <div class="relative flex min-h-32 cursor-pointer items-center gap-4 rounded-xl border border-dashed p-3 transition"
             :class="[
                 dragging
                     ? 'border-brand-500 bg-brand-50/60 dark:bg-brand-500/10'
                     : 'border-[var(--surface-border-strong)] hover:bg-[var(--surface-muted)]',
                 error && 'border-red-400',
             ]"
             @click="input?.click()"
             @dragover.prevent="dragging = true"
             @dragenter.prevent="dragging = true"
             @dragleave.prevent="dragging = false"
             @drop.prevent="drop">
            <img v-if="preview" :src="preview" alt=""
                 class="size-24 shrink-0 rounded-lg border border-[var(--surface-border)] object-cover">

            <span v-else class="flex size-24 shrink-0 items-center justify-center rounded-lg bg-[var(--surface-muted)] text-[var(--text-muted)]">
                <NIcon name="image" size="size-7" />
            </span>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-[var(--text-strong)]">
                    {{ preview ? 'Заменить картинку' : 'Перетащите картинку сюда' }}
                </p>
                <p class="mt-0.5 text-xs text-[var(--text-muted)]">
                    {{ preview ? 'Или перетащите новую — старая заменится.' : 'Или нажмите, чтобы выбрать файл.' }}
                </p>
                <p v-if="fileName && preview" class="mt-1 truncate text-xs text-[var(--text-faint)]">{{ fileName }}</p>
            </div>

            <button v-if="preview" type="button" title="Убрать картинку"
                    class="shrink-0 rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                    @click.stop="clear">
                <NIcon name="trash" size="size-4" />
            </button>

            <input ref="input" type="file" class="sr-only" :accept="accept" @change="pick">
        </div>
    </div>
</template>
