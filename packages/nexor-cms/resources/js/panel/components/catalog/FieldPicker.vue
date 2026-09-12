<script setup>
import { ref, watch } from 'vue';
import NButton from '../ui/NButton.vue';
import NModal from '../ui/NModal.vue';

/**
 * Попап «какие поля показывать»: список галочек и всё.
 *
 * Один на два места — колонки таблицы предложений и колонки списка выбора в
 * попапе предложения. Отмеченное применяется только по «Готово», чтобы можно
 * было передумать.
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    title: { type: String, default: 'Настроить поля' },
    description: { type: String, default: null },
    /** [{ key, label, hint }] */
    fields: { type: Array, default: () => [] },
    selected: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'update:selected']);

const chosen = ref([]);

function close() {
    emit('update:modelValue', false);
}

function apply() {
    emit('update:selected', [...chosen.value]);
    close();
}

// Открыли — показываем то, что выбрано сейчас, а не остатки прошлого раза.
watch(() => props.modelValue, (open) => {
    if (open) {
        chosen.value = [...props.selected];
    }
});
</script>

<template>
    <NModal :model-value="modelValue" :title="title" max-width="max-w-md" @update:model-value="close">
        <div class="space-y-3">
            <p v-if="description" class="text-sm text-[var(--text-muted)]">{{ description }}</p>

            <p v-if="!fields.length" class="rounded-lg bg-[var(--surface-muted)] p-3 text-sm text-[var(--text-muted)]">
                У этого инфоблока пока нет свойств — добавить в список нечего.
            </p>

            <div v-else class="max-h-72 space-y-2.5 overflow-y-auto">
                <label v-for="field in fields" :key="field.key"
                       class="flex cursor-pointer items-start gap-2.5 select-none">
                    <input type="checkbox" :value="field.key" v-model="chosen"
                           class="mt-0.5 size-4 shrink-0 rounded border-[var(--surface-border-strong)] text-brand-600 focus:ring-2 focus:ring-brand-500/40">

                    <span class="min-w-0">
                        <span class="block text-sm text-[var(--text-strong)]">{{ field.label }}</span>
                        <span v-if="field.hint" class="block text-xs text-[var(--text-muted)]">{{ field.hint }}</span>
                    </span>
                </label>
            </div>
        </div>

        <template #footer>
            <NButton v-if="chosen.length" variant="ghost" @click="chosen = []">Снять все</NButton>
            <NButton variant="secondary" @click="close">Отмена</NButton>
            <NButton @click="apply">Готово</NButton>
        </template>
    </NModal>
</template>
