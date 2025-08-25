import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
    build: {
        outDir: 'dist/assets',
        emptyOutDir: true,
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
        proxy: {
            '/api': {
                target: 'http://127.0.0.1:8000',
                changeOrigin: true,
                // optional: if PHP serves /api/register.php literally, no rewrite needed
                // rewrite: (path) => path.replace(/^\/api/, '/api'),
            },
        },
    },
});
