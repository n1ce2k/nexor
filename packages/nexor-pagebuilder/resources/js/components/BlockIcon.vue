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
    tabs: ['M3 8h18v12H3z', 'M3 8V5h6v3', 'M9 8V5h6v3'],
    gallery: ['M3 7h14v10H3z', 'M20 9v6', 'm7 14 3-3 2 2 2-2'],
    cart: ['M3 4h2l2.4 11h10.2L20 8H7', 'M10 20h.01', 'M17 20h.01'],
    links: ['M10 14a4 4 0 0 0 5.66 0l3-3a4 4 0 0 0-5.66-5.66l-1 1', 'M14 10a4 4 0 0 0-5.66 0l-3 3a4 4 0 0 0 5.66 5.66l1-1'],
    document: ['M7 3h7l5 5v13H7z', 'M14 3v5h5'],
};
</script>

<template>
    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path v-for="(d, index) in paths[name] ?? paths.document" :key="index" :d="d" />
    </svg>
</template>
