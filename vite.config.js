import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            vue: 'vue/dist/vue.esm-bundler.js',
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (!id.includes('node_modules')) {
                        return;
                    }

                    if (id.includes('chart.js')) {
                        return 'vendor-charts';
                    }

                    if (id.includes('ant-design-vue')) {
                        return 'vendor-antd';
                    }

                    if (id.includes('vue') || id.includes('@vue')) {
                        return 'vendor-vue';
                    }

                    return 'vendor-misc';
                },
            },
        },
    },
});
