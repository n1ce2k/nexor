<script setup>
import { computed } from 'vue';
import { NField, NHtmlInput, NInput, NToggle } from '../core.js';
import MediaInput from '../components/MediaInput.vue';
import Segmented from '../components/Segmented.vue';

/**
 * Видео по ссылке или своим файлом; вид: только плеер, с подписью, рядом с текстом.
 */
const props = defineProps({
    data: { type: Object, required: true },
    iblock: { type: Object, required: true },
});

props.data.sourceType ??= 'link';
props.data.layout ??= 'full';
props.data.reverse ??= false;
props.data.settings ??= {};
props.data.settings.autoplay ??= false;
props.data.settings.loop ??= false;
props.data.settings.muted ??= false;
props.data.settings.controls ??= true;

const sources = [
    { value: 'link', label: 'Ссылка' },
    { value: 'file', label: 'Файл' },
];

const layouts = [
    { value: 'full', label: 'Только видео' },
    { value: 'caption', label: 'С подписью' },
    { value: 'split', label: 'Видео и текст рядом' },
];

/**
 * Превью в панели — тем же разбором ссылки, что и на сервере (VideoEmbed):
 * сразу видно, распознана ли ссылка.
 */
const embed = computed(() => {
    const url = (props.data.url ?? '').trim();
    const rules = [
        [/^https?:\/\/(?:www\.|m\.)?youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/)([A-Za-z0-9_-]{11})/i, (m) => `https://www.youtube.com/embed/${m[1]}`],
        [/^https?:\/\/youtu\.be\/([A-Za-z0-9_-]{11})/i, (m) => `https://www.youtube.com/embed/${m[1]}`],
        [/^https?:\/\/(?:www\.|player\.)?vimeo\.com\/(?:video\/)?(\d{6,12})/i, (m) => `https://player.vimeo.com/video/${m[1]}`],
        [/^https?:\/\/(?:www\.)?rutube\.ru\/(?:video|play\/embed)\/([a-f0-9]{32})/i, (m) => `https://rutube.ru/play/embed/${m[1]}`],
        [/^https?:\/\/(?:www\.)?(?:vk\.com|vkvideo\.ru)\/video(-?\d+)_(\d+)/i, (m) => `https://vkvideo.ru/video_ext.php?oid=${m[1]}&id=${m[2]}`],
    ];

    for (const [pattern, build] of rules) {
        const match = url.match(pattern);

        if (match) {
            return build(match);
        }
    }

    return null;
});
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap gap-3">
            <Segmented v-model="data.sourceType" :options="sources" />
            <Segmented v-model="data.layout" :options="layouts" />
        </div>

        <div class="grid gap-5 md:grid-cols-[minmax(0,1fr)_18rem]">
            <div class="space-y-4">
                <NField v-if="data.sourceType === 'link'" label="Ссылка на видео"
                        hint="YouTube, Vimeo, Rutube или VK Видео — обычная ссылка из адресной строки."
                        :error="data.url && !embed ? 'Ссылка не распознана — проверьте адрес.' : null">
                    <NInput v-model="data.url" placeholder="https://www.youtube.com/watch?v=…" :invalid="Boolean(data.url && !embed)" />
                </NField>

                <div v-else class="grid gap-4 sm:grid-cols-2">
                    <NField label="Видеофайл" hint="MP4 или WebM, до 100 МБ.">
                        <MediaInput v-model="data.file" :iblock="iblock" kind="video" :with-alt="false" size="size-20" />
                    </NField>
                    <NField label="Обложка">
                        <MediaInput v-model="data.poster" :iblock="iblock" :with-alt="false" size="size-20" />
                    </NField>
                </div>

                <div class="flex flex-wrap gap-x-6 gap-y-2">
                    <NToggle v-model="data.settings.autoplay" label="Автозапуск" />
                    <NToggle v-model="data.settings.muted" label="Без звука" />
                    <NToggle v-model="data.settings.loop" label="По кругу" />
                    <NToggle v-model="data.settings.controls" label="Кнопки плеера" />
                </div>

                <NToggle v-if="data.layout === 'split'" v-model="data.reverse" label="Текст слева, видео справа" />
            </div>

            <div class="aspect-video overflow-hidden rounded-lg bg-[var(--surface-muted)]">
                <iframe v-if="data.sourceType === 'link' && embed" :src="embed" class="size-full border-0" loading="lazy" allowfullscreen></iframe>
                <video v-else-if="data.sourceType === 'file' && data.file?.src" :src="data.file.src" :poster="data.poster?.src" class="size-full" controls></video>
                <div v-else class="flex size-full items-center justify-center text-xs text-[var(--text-muted)]">Превью появится здесь</div>
            </div>
        </div>

        <NField v-if="data.layout !== 'full'" :label="data.layout === 'split' ? 'Текст рядом с видео' : 'Подпись под видео'">
            <NHtmlInput v-model="data.text" rows="8rem" />
        </NField>
    </div>
</template>
