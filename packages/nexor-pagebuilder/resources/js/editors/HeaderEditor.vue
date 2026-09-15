<script setup>
import { NField, NInput } from '../core.js';
import Segmented from '../components/Segmented.vue';

const props = defineProps({ data: { type: Object, required: true } });

props.data.tag ??= 'h2';

const tags = ['h2', 'h3', 'h4', 'h5'].map((tag) => ({ value: tag, label: tag.toUpperCase() }));
</script>

<template>
    <div class="flex flex-wrap items-end gap-3">
        <NField label="Текст заголовка" class="min-w-60 flex-1">
            <NInput v-model="data.text" placeholder="Например, «Что такое вышка-тура»" maxlength="255" />
        </NField>

        <Segmented v-model="data.tag" :options="tags" mono />
    </div>
</template>
