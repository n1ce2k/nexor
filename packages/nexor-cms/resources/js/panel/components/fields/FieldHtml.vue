<script setup>
import { computed } from 'vue';
import NHtmlInput from '../ui/NHtmlInput.vue';

/** Визуальный редактор для свойств типа HTML. */
const props = defineProps({
    modelValue: { type: [String, null], default: '' },
    property: { type: Object, required: true },
    invalid: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const settings = computed(() => props.property.settings ?? {});
</script>

<template>
    <NHtmlInput :model-value="modelValue ?? ''" :invalid="invalid"
                :placeholder="settings.placeholder ?? 'Начните вводить текст…'"
                :rows="settings.rows ? `${settings.rows}rem` : '16rem'"
                @update:model-value="emit('update:modelValue', $event)" />
</template>
