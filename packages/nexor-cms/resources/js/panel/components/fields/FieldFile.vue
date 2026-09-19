<script setup>
import { computed, ref } from 'vue';
import NIcon from '../ui/NIcon.vue';

/**
 * File and image properties.
 *
 * The value is a small object rather than a plain scalar, because the form has
 * to carry three things at once: what is already stored, what the user removed,
 * and what they just picked.
 *
 *   { stored: [{ id, path, url }], remove: [id], added: [File] }
 */
const props = defineProps({
    modelValue: { type: Object, default: () => ({ stored: [], remove: [], added: [] }) },
    property: { type: Object, required: true },
});

const emit = defineEmits(['update:modelValue']);

const input = ref(null);
const dragging = ref(false);

const value = computed(() => ({
    stored: props.modelValue?.stored ?? [],
    remove: props.modelValue?.remove ?? [],
    added: props.modelValue?.added ?? [],
    // Подписи к только что выбранным файлам — по порядку, как сами файлы.
    addedDescriptions: props.modelValue?.addedDescriptions ?? [],
}));

const describes = computed(() => Boolean(props.property.with_description));

/** Описание сохранённого файла живёт прямо в его строке. */
function describeStored(id, text) {
    emit('update:modelValue', {
        ...value.value,
        stored: value.value.stored.map((file) => (file.id === id ? { ...file, description: text } : file)),
    });
}

function describeAdded(index, text) {
    const next = [...value.value.addedDescriptions];
    next[index] = text;

    emit('update:modelValue', { ...value.value, addedDescriptions: next });
}

const isImage = computed(() => props.property.type === 'image');

const accept = computed(() => props.property.settings?.accept ?? (isImage.value ? 'image/*' : null));

const visible = computed(() => value.value.stored.filter((file) => !value.value.remove.includes(file.id)));

function pick(event) {
    take(Array.from(event.target.files ?? []));
    event.target.value = '';
}

function drop(event) {
    dragging.value = false;
    take(Array.from(event.dataTransfer?.files ?? []));
}

/**
 * Файлы приходят одинаково — из окна выбора и перетаскиванием.
 *
 * Картинке чужие форматы не нужны: перетащить PDF в поле «изображение» проще,
 * чем выбрать его в окне, которое само отфильтровало бы список.
 */
function take(picked) {
    const files = isImage.value ? picked.filter((file) => file.type.startsWith('image/')) : picked;

    if (files.length === 0) {
        return;
    }

    const single = !props.property.is_multiple;

    emit('update:modelValue', {
        ...value.value,
        // Одиночное поле заменяет прежний файл: старый уходит на удаление.
        remove: single
            ? [...new Set([...value.value.remove, ...value.value.stored.map((file) => file.id)])]
            : value.value.remove,
        added: single ? [files[0]] : [...value.value.added, ...files],
    });
}

function removeStored(id) {
    emit('update:modelValue', { ...value.value, remove: [...value.value.remove, id] });
}

function removeAdded(index) {
    emit('update:modelValue', {
        ...value.value,
        added: value.value.added.filter((_, i) => i !== index),
        addedDescriptions: value.value.addedDescriptions.filter((_, i) => i !== index),
    });
}

function preview(file) {
    return URL.createObjectURL(file);
}
</script>

<template>
    <div class="space-y-3">
        <div v-for="file in visible" :key="file.id"
             class="flex items-center gap-3 rounded-lg border border-[var(--surface-border)] p-2">
            <img v-if="isImage && file.url" :src="file.url" alt="" class="size-14 rounded object-cover">
            <span v-else class="flex size-14 items-center justify-center rounded bg-[var(--surface-muted)] text-[var(--text-muted)]">
                <NIcon name="document" size="size-5" />
            </span>

            <div class="min-w-0 flex-1 space-y-1.5">
                <a :href="file.url" target="_blank" rel="noopener"
                   class="block truncate text-sm text-brand-600 hover:underline dark:text-brand-400">
                    {{ file.path.split('/').pop() }}
                </a>

                <input v-if="describes" type="text" class="field-input" placeholder="Описание"
                       :value="file.description ?? ''"
                       @input="describeStored(file.id, $event.target.value)">
            </div>

            <button type="button" class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                    @click="removeStored(file.id)">
                <NIcon name="trash" size="size-4" />
            </button>
        </div>

        <div v-for="(file, index) in value.added" :key="`new-${index}`"
             class="flex items-center gap-3 rounded-lg border border-dashed border-brand-400 p-2">
            <img v-if="isImage" :src="preview(file)" alt="" class="size-14 rounded object-cover">
            <span v-else class="flex size-14 items-center justify-center rounded bg-[var(--surface-muted)] text-[var(--text-muted)]">
                <NIcon name="document" size="size-5" />
            </span>

            <div class="min-w-0 flex-1 space-y-1.5">
                <span class="block truncate text-sm text-[var(--text-base)]">{{ file.name }}</span>

                <input v-if="describes" type="text" class="field-input" placeholder="Описание"
                       :value="value.addedDescriptions[index] ?? ''"
                       @input="describeAdded(index, $event.target.value)">
            </div>

            <button type="button" class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                    @click="removeAdded(index)">
                <NIcon name="x" size="size-4" />
            </button>
        </div>

        <div class="flex cursor-pointer items-center gap-3 rounded-xl border border-dashed p-3 transition"
             :class="dragging
                 ? 'border-brand-500 bg-brand-50/60 dark:bg-brand-500/10'
                 : 'border-[var(--surface-border-strong)] hover:bg-[var(--surface-muted)]'"
             @click="input?.click()"
             @dragover.prevent="dragging = true"
             @dragenter.prevent="dragging = true"
             @dragleave.prevent="dragging = false"
             @drop.prevent="drop">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-[var(--surface-muted)] text-[var(--text-muted)]">
                <NIcon :name="isImage ? 'image' : 'document'" size="size-5" />
            </span>

            <div class="min-w-0">
                <p class="text-sm font-medium text-[var(--text-strong)]">
                    {{ visible.length || value.added.length
                        ? (property.is_multiple ? 'Перетащите ещё' : 'Перетащите, чтобы заменить')
                        : 'Перетащите сюда' }}
                    {{ isImage ? 'картинку' : 'файл' }}{{ property.is_multiple ? 'ы' : '' }}
                </p>
                <p class="mt-0.5 text-xs text-[var(--text-muted)]">Или нажмите, чтобы выбрать.</p>
            </div>

            <input ref="input" type="file" class="sr-only" :accept="accept"
                   :multiple="property.is_multiple" @change="pick">
        </div>
    </div>
</template>
