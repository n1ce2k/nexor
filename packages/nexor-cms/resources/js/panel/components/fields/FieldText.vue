<script setup>
import { computed } from 'vue';
import NInput from '../ui/NInput.vue';

/**
 * Single-line editor for string, integer, decimal, date, datetime and colour
 * properties — everything that maps onto one native input.
 */
const props = defineProps({
    modelValue: { type: [String, Number, null], default: '' },
    property: { type: Object, required: true },
    invalid: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const inputType = computed(() => ({
    integer: 'number',
    decimal: 'number',
    date: 'date',
    datetime: 'datetime-local',
}[props.property.type] ?? 'text'));

const settings = computed(() => props.property.settings ?? {});

const step = computed(() => {
    if (props.property.type === 'decimal') {
        return settings.value.step ?? 'any';
    }

    return props.property.type === 'integer' ? (settings.value.step ?? 1) : null;
});

const isColor = computed(() => props.property.type === 'color');

const swatch = computed(() => {
    const value = String(props.modelValue ?? '').trim();

    return /^#?[0-9a-f]{6}$/i.test(value) ? (value.startsWith('#') ? value : `#${value}`) : '#000000';
});

/** Trim a datetime-local value the browser hands back with seconds. */
function normalise(value) {
    return props.property.type === 'datetime' && typeof value === 'string' ? value.slice(0, 16) : value;
}
</script>

<template>
    <div v-if="isColor" class="flex items-center gap-2">
        <label class="relative size-9 shrink-0 cursor-pointer overflow-hidden rounded-lg border border-[var(--surface-border-strong)]"
               :style="{ backgroundColor: swatch }">
            <input type="color" :value="swatch" class="absolute inset-0 size-full cursor-pointer opacity-0"
                   @input="emit('update:modelValue', $event.target.value.toUpperCase())">
        </label>

        <NInput :model-value="modelValue ?? ''" :invalid="invalid" placeholder="#FFFFFF" maxlength="7"
                class="font-mono uppercase"
                @update:model-value="emit('update:modelValue', $event)" />
    </div>

    <NInput v-else
            :model-value="modelValue ?? ''"
            :type="inputType"
            :invalid="invalid"
            :placeholder="settings.placeholder"
            :maxlength="settings.max_length"
            :min="settings.min"
            :max="settings.max"
            :step="step"
            :suffix="settings.suffix"
            @update:model-value="emit('update:modelValue', normalise($event))" />
</template>
