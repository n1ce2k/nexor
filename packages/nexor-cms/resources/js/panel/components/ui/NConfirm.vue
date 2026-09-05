<script setup>
import { computed } from 'vue';
import NButton from './NButton.vue';
import NModal from './NModal.vue';
import { useUi } from '../../stores/ui';

const ui = useUi();

const open = computed({
    get: () => ui.confirmation !== null,
    set: (value) => {
        if (!value) {
            ui.confirmation?.resolve(false);
        }
    },
});
</script>

<template>
    <NModal v-model="open" :title="ui.confirmation?.title" max-width="max-w-md">
        <p class="text-sm text-[var(--text-base)]">{{ ui.confirmation?.message }}</p>

        <template #footer>
            <NButton variant="secondary" size="sm" @click="ui.confirmation?.resolve(false)">Отмена</NButton>
            <NButton :variant="ui.confirmation?.danger ? 'danger' : 'primary'" size="sm"
                     @click="ui.confirmation?.resolve(true)">
                {{ ui.confirmation?.confirmLabel }}
            </NButton>
        </template>
    </NModal>
</template>
