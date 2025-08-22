import { defineConfig } from "vite";
import { resolve } from "path";

export default defineConfig({
    build: {
        outDir: "dist/assets",
        emptyOutDir: true,
        rollupOptions: {
            input: resolve(__dirname, "src/main.ts"),
            output: {
                entryFileNames: "[name]-[hash].js",
                chunkFileNames: "[name]-[hash].js",
                assetFileNames: "[name]-[hash][extname]",
            },
        },
    },
});
