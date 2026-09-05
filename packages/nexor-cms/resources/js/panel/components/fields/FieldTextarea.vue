<script setup>
import { computed } from 'vue';

/** Multi-line editor for text, HTML and JSON properties. */
const props = defineProps({
    modelValue: { type: [String, Object, Array, null], default: '' },
    property: { type: Object, required: true },
    invalid: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const settings = computed(() => props.property.settings ?? {});

const rows = computed(() => settings.value.rows ?? (props.property.type === 'html' ? 10 : 4));

const mono = computed(() => props.property.type === 'html' || props.property.type === 'json');

/** JSON values arrive decoded; the editor works on the pretty-printed text. */
const text = computed(() => {
    if (props.property.type === 'json' && props.modelValue && typeof props.modelValue === 'object') {
        return JSON.stringify(props.modelValue, null, 2);
    }

    return props.modelValue ?? '';
});
</script>

<template>
    <textarea :value="text" :rows="rows"
              :placeholder="settings.placeholder"
              :class="['field-input resize-y', invalid && 'has-error', mono && 'font-mono text-xs']"
              @input="emit('update:modelValue', $event.target.value)"></textarea>
</template>
