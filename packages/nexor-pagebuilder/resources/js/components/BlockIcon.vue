<script setup>
/**
 * Иконки палитры блоков. Имя приходит с сервера (`Block::icon()`); незнакомое
 * имя рисуется как документ.
 */
defineProps({ name: { type: String, default: 'document' } });

const paths = {
    heading: ['M6 4v16', 'M18 4v16', 'M6 12h12'],
    text: ['M4 6h16', 'M4 12h16', 'M4 18h10'],
    quote: ['M7 7h4v4c0 3-1.5 5-4 6', 'M15 7h4v4c0 3-1.5 5-4 6'],
    'panel-left': ['M3 5h7v14H3z', 'M14 7h7', 'M14 12h7', 'M14 17h5'],
    image: ['M4 5h16v14H4z', 'm4 16 5-5 4 4 3-3 4 4', 'M15.5 9.5h.01'],
    video: ['M3 6h12v12H3z', 'm15 10 6-3v10l-6-3'],
    list: ['M4 6h16', 'M4 12h16', 'M4 18h16', 'm17 4 2 2-2 2'],
    table: ['M3 5h18v14H3z', 'M3 10h18', 'M3 15h18', 'M9 5v14', 'M15 5v14'],
    document: ['M7 3h7l5 5v13H7z', 'M14 3v5h5'],
};
</script>

<template>
    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path v-for="(d, index) in paths[name] ?? paths.document" :key="index" :d="d" />
    </svg>
</template>
