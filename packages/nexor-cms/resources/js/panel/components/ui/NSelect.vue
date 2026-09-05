<script setup>
defineProps({
    modelValue: { type: [String, Number, null], default: '' },
    // [{ value, label }] or [{ label, options: [...] }] for grouped lists
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: null },
    invalid: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <select :value="modelValue" :disabled="disabled"
            :class="['field-input pr-9', invalid && 'has-error']"
            @change="$emit('update:modelValue', $event.target.value === '' ? null : $event.target.value)">
        <option v-if="placeholder" value="">{{ placeholder }}</option>

        <template v-for="(option, index) in options" :key="index">
            <optgroup v-if="option.options" :label="option.label">
                <option v-for="inner in option.options" :key="inner.value" :value="inner.value">
                    {{ inner.label }}
                </option>
            </optgroup>
            <option v-else :value="option.value">{{ option.label }}</option>
        </template>
    </select>
</template>
