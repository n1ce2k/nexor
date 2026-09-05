<script setup>
import { computed } from 'vue';
import NSelect from '../ui/NSelect.vue';

/**
 * Dropdown for list properties and for links to elements, sections and users.
 * The choices come from the schema endpoint, so no lookup happens here.
 */
const props = defineProps({
    modelValue: { type: [String, Number, null], default: null },
    property: { type: Object, required: true },
    options: { type: Array, default: () => [] },
    invalid: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const choices = computed(() => {
    if (props.property.type === 'select') {
        return (props.property.enums ?? []).map((item) => ({ value: item.id, label: item.value }));
    }

    return props.options ?? [];
});
</script>

<template>
    <NSelect :model-value="modelValue" :options="choices" :invalid="invalid"
             placeholder="— не выбрано —"
             @update:model-value="emit('update:modelValue', $event)" />
</template>
