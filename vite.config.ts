import { defineConfig } from 'vite';

export default defineConfig({
  build: {
    outDir: 'public/assets',
    emptyOutDir: true,
    rollupOptions: {
      output: {
        entryFileNames: '[name]-[hash].js',
        chunkFileNames: '[name]-[hash].js',
        assetFileNames: '[name]-[hash][extname]'
      }
    }
  }
});
