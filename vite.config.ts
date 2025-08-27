import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
    build: {
        outDir: 'server/public/assets',
        emptyOutDir: true,
        manifest: true, 
        rollupOptions: {
            input: resolve(__dirname, 'src/main.ts'),
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
