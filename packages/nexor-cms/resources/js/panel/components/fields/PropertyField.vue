<script setup>
import { computed } from 'vue';
import NField from '../ui/NField.vue';
import NIcon from '../ui/NIcon.vue';
import { resolveField } from '../../registry';

/**
 * Renders one infoblock property.
 *
 * The concrete editor is looked up in the registry by property type, so adding
 * a new type is `registerField('geo', MyMapField)` and nothing here changes.
 * Multiple properties are handled once, at this level, rather than in every
 * editor: the field component always deals with a single value.
 */
const props = defineProps({
    property: { type: Object, required: true },
    modelValue: { type: [String, Number, Boolean, Object, Array, null], default: null },
    options: { type: Array, default: () => [] },
    error: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const component = computed(() => resolveField(props.property.type));

const isFile = computed(() => props.property.type === 'file' || props.property.type === 'image');

// File properties keep their own multi-value shape, so the repeater is skipped.
const repeats = computed(() => props.property.is_multiple && !isFile.value);

const rows = computed(() => {
    if (!repeats.value) {
        return [];
    }

    const value = Array.isArray(props.modelValue) ? props.modelValue : [];

    return value.length ? value : [null];
});

function updateRow(index, value) {
    const next = [...rows.value];
    next[index] = value;
    emit('update:modelValue', next);
}

function addRow() {
    emit('update:modelValue', [...rows.value, null]);
}

function removeRow(index) {
    const next = rows.value.filter((_, i) => i !== index);
    emit('update:modelValue', next.length ? next : [null]);
}
</script>

<template>
    <NField :label="property.name" :hint="property.hint" :required="property.is_required" :error="error">
        <p v-if="property.description" class="text-xs whitespace-pre-line text-[var(--text-muted)]">
            {{ property.description }}
        </p>

        <div v-if="repeats" class="space-y-2">
            <div v-for="(row, index) in rows" :key="index" class="flex items-start gap-2">
                <div class="min-w-0 flex-1">
                    <component :is="component" :property="property" :options="options"
                               :model-value="row" :invalid="Boolean(error)"
                               @update:model-value="updateRow(index, $event)" />
                </div>

                <button type="button"
                        class="mt-0.5 shrink-0 rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                        @click="removeRow(index)">
                    <NIcon name="trash" size="size-4" />
                </button>
            </div>

            <button type="button"
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-brand-600 transition hover:underline dark:text-brand-400"
                    @click="addRow">
                <NIcon name="plus" size="size-3.5" />
                Добавить значение
            </button>
        </div>

        <component v-else :is="component" :property="property" :options="options"
                   :model-value="modelValue" :invalid="Boolean(error)"
                   @update:model-value="emit('update:modelValue', $event)" />
    </NField>
</template>
