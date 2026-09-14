import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

/**
 * Готовая сборка страниц магазина в панели — vendor/n1ce2k/nexor-shop/dist.
 *
 * Vue и vue-router в сборку не входят: страницы магазина берут их у панели
 * ядра (window.Nexor.vendor), иначе на странице было бы две копии Vue.
 */
export default defineConfig({
    root: 'packages/nexor-shop',
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
            name: 'NexorShopPanel',
            fileName: () => 'panel.js',
            cssFileName: 'panel',
        },
        rollupOptions: {
            external: ['vue', 'vue-router'],
            output: {
                globals: {
                    vue: 'Nexor.vendor.vue',
                    'vue-router': 'Nexor.vendor.vueRouter',
                },
            },
        },
    },
});
