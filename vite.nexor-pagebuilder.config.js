import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

/**
 * Готовая сборка редактора конструктора в панели — vendor/n1ce2k/nexor-pagebuilder/dist.
 *
 * Vue и vue-router в сборку не входят: редактор берёт их у панели
 * ядра (window.Nexor.vendor), иначе на странице было бы две копии Vue.
 */
export default defineConfig({
    root: 'packages/nexor-pagebuilder',
    plugins: [tailwindcss(), vue()],
    define: {
        'process.env.NODE_ENV': JSON.stringify('production'),
    },
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        cssCodeSplit: false,
        lib: {
            entry: 'resources/js/panel.js',
            formats: ['iife'],
            name: 'NexorPageBuilderPanel',
            fileName: () => 'panel.js',
            cssFileName: 'panel',
        },
        rollupOptions: {
            external: ['vue', 'vue-router', 'vuedraggable'],
            output: {
                globals: {
                    vue: 'Nexor.vendor.vue',
                    'vue-router': 'Nexor.vendor.vueRouter',
                    vuedraggable: 'Nexor.vendor.draggable',
                },
            },
        },
    },
});
