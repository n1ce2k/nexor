<script setup>
/**
 * Переключатель из нескольких вариантов: тег заголовка, раскладка, вид видео.
 */
defineProps({
    modelValue: { type: [String, Number, Boolean], default: null },
    options: { type: Array, required: true },
    mono: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <div class="inline-flex flex-wrap rounded-lg border border-[var(--surface-border)] p-0.5">
        <button v-for="option in options" :key="String(option.value)" type="button"
                :class="['rounded-md px-3 py-1.5 text-xs font-semibold transition', mono && 'font-mono',
                         modelValue === option.value ? 'bg-brand-600 text-white' : 'text-[var(--text-muted)] hover:text-[var(--text-strong)]']"
                @click="emit('update:modelValue', option.value)">
            {{ option.label }}
        </button>
    </div>
</template>
