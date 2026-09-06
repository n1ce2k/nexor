<script setup>
/**
 * Вкладки формы.
 *
 * У вкладки может быть отметка: если валидация не прошла на скрытой вкладке,
 * ошибку иначе не найти.
 */
defineProps({
    // [{ key, label, mark: Boolean }]
    tabs: { type: Array, required: true },
    modelValue: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <div class="-mb-px flex flex-wrap items-center gap-1 overflow-x-auto border-b border-[var(--surface-border)]"
         role="tablist">
        <button v-for="tab in tabs" :key="tab.key" type="button" role="tab"
                :aria-selected="tab.key === modelValue"
                :class="['relative flex shrink-0 items-center gap-1.5 border-b-2 px-4 py-2.5 text-sm font-medium transition',
                         tab.key === modelValue
                             ? 'border-brand-600 text-brand-600 dark:border-brand-400 dark:text-brand-400'
                             : 'border-transparent text-[var(--text-muted)] hover:text-[var(--text-strong)]']"
                @click="emit('update:modelValue', tab.key)">
            {{ tab.label }}

            <span v-if="tab.mark" class="size-1.5 rounded-full bg-red-500" title="На вкладке есть ошибки"></span>
        </button>

        <div v-if="$slots.actions" class="ml-auto flex shrink-0 items-center gap-2 pb-1.5 pl-3">
            <slot name="actions" />
        </div>
    </div>
</template>
