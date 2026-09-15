<script setup>
import { NField, NHtmlInput } from '../core.js';
import MediaInput from '../components/MediaInput.vue';
import Segmented from '../components/Segmented.vue';

const props = defineProps({
    data: { type: Object, required: true },
    iblock: { type: Object, required: true },
});

props.data.imagePosition ??= 'left';

const positions = [
    { value: 'left', label: 'Фото слева' },
    { value: 'right', label: 'Фото справа' },
];
</script>

<template>
    <div class="grid gap-5 md:grid-cols-[18rem_minmax(0,1fr)]">
        <div class="space-y-3">
            <NField label="Фото">
                <MediaInput v-model="data.image" :iblock="iblock" with-title />
            </NField>

            <Segmented v-model="data.imagePosition" :options="positions" />
        </div>

        <NField label="Текст">
            <NHtmlInput v-model="data.text" rows="14rem" />
        </NField>
    </div>
</template>
