<script setup>
import NButton from './NButton.vue';
import NIcon from './NIcon.vue';

defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Поиск...' },
    dirty: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'apply', 'reset']);
</script>

<template>
    <form class="flex flex-wrap items-end gap-3" @submit.prevent="emit('apply')">
        <div class="relative min-w-0 flex-1 sm:max-w-xs">
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-[var(--text-faint)]">
                <NIcon name="search" size="size-4" />
            </span>

            <input type="search" :value="modelValue" :placeholder="placeholder" class="field-input pl-9"
                   @input="emit('update:modelValue', $event.target.value)">
        </div>

        <slot />

        <div class="flex items-center gap-2">
            <NButton type="submit" variant="secondary" icon="filter">Применить</NButton>
            <NButton v-if="dirty" variant="ghost" @click="emit('reset')">Сбросить</NButton>
        </div>
    </form>
</template>
