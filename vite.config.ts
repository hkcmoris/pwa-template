import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
    build: {
        outDir: 'server/public/assets',
        emptyOutDir: true,
        manifest: true,
        target: 'es2020',
        modulePreload: { polyfill: false },
        rollupOptions: {
            input: {
                main: resolve(__dirname, 'src/main.ts'),
                fonts: resolve(__dirname, 'src/styles/fonts.css'),
            },
            output: {
                entryFileNames: '[name]-[hash].js',
                chunkFileNames: '[name]-[hash].js',
                assetFileNames: '[name]-[hash][extname]',
            },
        },
    },
    server: {
        port: 5173,
        strictPort: true,
    },
});
