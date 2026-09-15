<script setup>
import { NField, NHtmlInput, NInput, NToggle } from '../core.js';
import MediaInput from '../components/MediaInput.vue';

defineProps({
    data: { type: Object, required: true },
    iblock: { type: Object, required: true },
});
</script>

<template>
    <div class="space-y-4">
        <NField label="Текст цитаты">
            <NHtmlInput v-model="data.text" rows="8rem" />
        </NField>

        <NToggle v-model="data.showAuthor" label="Показать автора" />

        <div v-if="data.showAuthor" class="grid gap-4 md:grid-cols-[auto_minmax(0,1fr)]">
            <NField label="Фото автора">
                <MediaInput v-model="data.avatar" :iblock="iblock" :with-alt="false" size="size-20" />
            </NField>

            <div class="grid gap-4 sm:grid-cols-2">
                <NField label="Автор">
                    <NInput v-model="data.author" placeholder="Иван Петров" />
                </NField>
                <NField label="Должность или подпись">
                    <NInput v-model="data.role" placeholder="Главный инженер" />
                </NField>
            </div>
        </div>
    </div>
</template>
