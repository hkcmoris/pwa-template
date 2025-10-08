import { defineConfig, loadEnv } from 'vite';
import { resolve } from 'path';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const rawBase = (env.APP_BASE || process.env.APP_BASE || '').trim();
    const normalized = rawBase ? `/${rawBase.replace(/^\/+|\/+$/g, '')}` : '';
    const base = mode === 'production' ? `${normalized}/public/assets/` : '/';

    console.log(
        `Vite config: mode=${mode}, APP_BASE='${rawBase}' => base='${base}'`
    );

    return {
        base,
        build: {
            outDir: 'server/public/assets',
            emptyOutDir: true,
            manifest: true,
            target: 'es2020',
            modulePreload: { polyfill: false },
            rollupOptions: {
                input: {
                    main: resolve(__dirname, 'src/main.ts'),
                    layout: resolve(__dirname, 'src/styles/layout.css'),
                    fonts: resolve(__dirname, 'src/styles/fonts.css'),
                    'editor-definitions': resolve(
                        __dirname,
                        'src/styles/editor/definitions.css'
                    ),
                    'editor-components': resolve(
                        __dirname,
                        'src/styles/editor/components.css'
                    ),
                    'editor-images': resolve(
                        __dirname,
                        'src/styles/editor/images.css'
                    ),
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
    };
});
