<script setup>
import { computed } from 'vue';
import NToggle from '../ui/NToggle.vue';

const props = defineProps({
    modelValue: { type: [Boolean, Number, String, null], default: false },
    property: { type: Object, required: true },
});

const emit = defineEmits(['update:modelValue']);

// Values arriving from the API may be 0/1 or "0"/"1" depending on the column.
const checked = computed(() => props.modelValue === true || props.modelValue === 1 || props.modelValue === '1');
</script>

<template>
    <NToggle :model-value="checked" label="Да" @update:model-value="emit('update:modelValue', $event)" />
</template>
