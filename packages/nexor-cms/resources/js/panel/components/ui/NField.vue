<script setup>
import NIcon from './NIcon.vue';

defineProps({
    label: { type: String, default: null },
    hint: { type: String, default: null },
    error: { type: String, default: null },
    required: { type: Boolean, default: false },
});
</script>

<template>
    <div class="space-y-1.5">
        <label v-if="label" class="block text-sm font-medium text-[var(--text-strong)]">
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </label>

        <slot />

        <p v-if="hint && !error" class="text-xs text-[var(--text-muted)]">{{ hint }}</p>

        <p v-if="error" class="flex items-center gap-1 text-xs text-red-600 dark:text-red-400">
            <NIcon name="alert" size="size-3.5" />
            {{ error }}
        </p>
    </div>
</template>
