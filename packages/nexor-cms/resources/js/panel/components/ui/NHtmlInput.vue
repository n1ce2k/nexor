<script setup>
import { ref } from 'vue';
import NEditor from './NEditor.vue';
import NIcon from './NIcon.vue';

/**
 * Поле для HTML: визуальный редактор с переключением в исходный код.
 *
 * Исходник нужен не реже редактора — разметку, вставленную из шаблона или из
 * другой CMS, правят руками, а Quill её переформатирует.
 */
defineProps({
    modelValue: { type: [String, null], default: '' },
    placeholder: { type: String, default: 'Начните вводить текст…' },
    rows: { type: String, default: '16rem' },
    invalid: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const source = ref(false);
</script>

<template>
    <div :class="['space-y-2', invalid && 'rounded-lg ring-2 ring-red-500/30']">
        <NEditor v-if="!source" :model-value="modelValue ?? ''" :placeholder="placeholder" :rows="rows"
                 @update:model-value="emit('update:modelValue', $event)" />

        <textarea v-else :value="modelValue ?? ''" :style="{ height: rows }"
                  class="field-input resize-y font-mono text-xs"
                  @input="emit('update:modelValue', $event.target.value)"></textarea>

        <button type="button"
                class="inline-flex items-center gap-1.5 text-xs font-medium text-[var(--text-muted)] transition hover:text-brand-600"
                @click="source = !source">
            <NIcon name="code" size="size-3.5" />
            {{ source ? 'Визуальный редактор' : 'Исходный код' }}
        </button>
    </div>
</template>
