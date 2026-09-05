<script setup>
defineProps({
    modelValue: { type: [String, Number], default: '' },
    type: { type: String, default: 'text' },
    placeholder: { type: String, default: null },
    suffix: { type: String, default: null },
    invalid: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    min: { type: [String, Number], default: null },
    max: { type: [String, Number], default: null },
    step: { type: [String, Number], default: null },
    maxlength: { type: [String, Number], default: null },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div v-if="suffix" class="flex items-stretch">
        <input :value="modelValue" :type="type" :placeholder="placeholder" :disabled="disabled"
               :min="min" :max="max" :step="step" :maxlength="maxlength"
               :class="['field-input rounded-r-none', invalid && 'has-error']"
               @input="$emit('update:modelValue', $event.target.value)">
        <span class="inline-flex items-center rounded-r-lg border border-l-0 border-[var(--surface-border-strong)] bg-[var(--surface-muted)] px-3 text-sm text-[var(--text-muted)]">
            {{ suffix }}
        </span>
    </div>

    <input v-else :value="modelValue" :type="type" :placeholder="placeholder" :disabled="disabled"
           :min="min" :max="max" :step="step" :maxlength="maxlength"
           :class="['field-input', invalid && 'has-error']"
           @input="$emit('update:modelValue', $event.target.value)">
</template>
