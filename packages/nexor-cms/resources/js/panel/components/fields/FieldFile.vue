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

const value = computed(() => ({
    stored: props.modelValue?.stored ?? [],
    remove: props.modelValue?.remove ?? [],
    added: props.modelValue?.added ?? [],
}));

const isImage = computed(() => props.property.type === 'image');

const accept = computed(() => props.property.settings?.accept ?? (isImage.value ? 'image/*' : null));

const visible = computed(() => value.value.stored.filter((file) => !value.value.remove.includes(file.id)));

function pick(event) {
    const files = Array.from(event.target.files ?? []);

    if (files.length === 0) {
        return;
    }

    emit('update:modelValue', {
        ...value.value,
        added: props.property.is_multiple ? [...value.value.added, ...files] : [files[0]],
    });

    // Replacing a single-value file drops whatever was stored before.
    if (!props.property.is_multiple && value.value.stored.length) {
        emit('update:modelValue', {
            stored: value.value.stored,
            remove: value.value.stored.map((file) => file.id),
            added: [files[0]],
        });
    }

    event.target.value = '';
}

function removeStored(id) {
    emit('update:modelValue', { ...value.value, remove: [...value.value.remove, id] });
}

function removeAdded(index) {
    emit('update:modelValue', { ...value.value, added: value.value.added.filter((_, i) => i !== index) });
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

            <a :href="file.url" target="_blank" rel="noopener"
               class="min-w-0 flex-1 truncate text-sm text-brand-600 hover:underline dark:text-brand-400">
                {{ file.path.split('/').pop() }}
            </a>

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

            <span class="min-w-0 flex-1 truncate text-sm text-[var(--text-base)]">{{ file.name }}</span>

            <button type="button" class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                    @click="removeAdded(index)">
                <NIcon name="x" size="size-4" />
            </button>
        </div>

        <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-[var(--surface-border-strong)] px-3 py-2 text-sm font-medium text-[var(--text-base)] transition hover:bg-[var(--surface-muted)]">
            <NIcon name="upload" size="size-4" />
            {{ visible.length || value.added.length ? 'Добавить ещё' : 'Выбрать файл' }}
            <input ref="input" type="file" class="sr-only" :accept="accept"
                   :multiple="property.is_multiple" @change="pick">
        </label>
    </div>
</template>
