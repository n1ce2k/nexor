import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

/**
 * Готовая сборка панели ядра — то, что приходит в vendor/n1ce2k/nexor-cms/dist.
 *
 * Сайту на NEXOR не нужен Node, чтобы открыть админку: пакет отдаёт эти файлы
 * сам. Пересобирать перед каждым релизом: npm run build:packages.
 */
export default defineConfig({
    root: 'packages/nexor-cms',
    // Относительный base: пакет не знает, под каким префиксом его отдадут.
    base: './',
    plugins: [tailwindcss(), vue()],
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        manifest: 'manifest.json',
        rollupOptions: {
            input: [
                'resources/js/panel/main.js',
                'resources/css/admin.css',
                'resources/js/admin.js',
            ],
        },
    },
});
