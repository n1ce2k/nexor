<script setup>
import { NField, NHtmlInput } from '../core.js';
import Segmented from '../components/Segmented.vue';

const props = defineProps({ data: { type: Object, required: true } });

props.data.cols = Number(props.data.cols ?? 1);

const columns = [
    { value: 1, label: '1 колонка' },
    { value: 2, label: '2 колонки' },
];
</script>

<template>
    <div class="space-y-3">
        <Segmented v-model="data.cols" :options="columns" />

        <div :class="['grid gap-4', data.cols === 2 && 'lg:grid-cols-2']">
            <NField :label="data.cols === 2 ? 'Левая колонка' : null">
                <NHtmlInput v-model="data.text" rows="14rem" />
            </NField>
            <NField v-if="data.cols === 2" label="Правая колонка">
                <NHtmlInput v-model="data.text2" rows="14rem" />
            </NField>
        </div>
    </div>
</template>
