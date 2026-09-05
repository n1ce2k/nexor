<script setup>
defineProps({
    modelValue: { type: Boolean, default: false },
    label: { type: String, default: null },
    hint: { type: String, default: null },
    disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <label class="flex cursor-pointer items-center justify-between gap-4 select-none"
           :class="disabled && 'cursor-not-allowed opacity-60'">
        <span class="text-sm">
            <span v-if="label" class="font-medium text-[var(--text-strong)]">{{ label }}</span>
            <span v-if="hint" class="mt-0.5 block text-xs font-normal text-[var(--text-muted)]">{{ hint }}</span>
        </span>

        <span class="relative inline-flex shrink-0">
            <input type="checkbox" class="peer sr-only" :checked="modelValue" :disabled="disabled"
                   @change="$emit('update:modelValue', $event.target.checked)">
            <span class="h-6 w-11 rounded-full bg-[var(--surface-border-strong)] transition peer-checked:bg-brand-600 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500/40 peer-focus-visible:ring-offset-2"></span>
            <span class="pointer-events-none absolute top-0.5 left-0.5 size-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
        </span>
    </label>
</template>
