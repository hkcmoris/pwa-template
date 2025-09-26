const js = require('@eslint/js');
const tsPlugin = require('@typescript-eslint/eslint-plugin');
const tsParser = require('@typescript-eslint/parser');
const globals = require('globals');
const eslintConfigPrettier = require('eslint-config-prettier');

const tsLanguageOptions = {
    parser: tsParser,
    parserOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
    },
};

const tsRecommendedRules = tsPlugin.configs.recommended.rules;

module.exports = [
    {
        ignores: ['dist/**', 'public/assets/**'],
    },
    js.configs.recommended,
    {
        files: ['src/**/*.{ts,tsx}'],
        languageOptions: {
            ...tsLanguageOptions,
            globals: {
                ...globals.browser,
            },
        },
        plugins: {
            '@typescript-eslint': tsPlugin,
        },
        rules: {
            ...tsRecommendedRules,
        },
    },
    {
        files: [
            'src/**/*.{test.ts,test.tsx,spec.ts,spec.tsx}',
            'src/**/__tests__/**/*.{ts,tsx}',
        ],
        languageOptions: {
            ...tsLanguageOptions,
            globals: {
                ...globals.browser,
                ...globals.node,
                ...globals.jest,
            },
        },
        plugins: {
            '@typescript-eslint': tsPlugin,
        },
        rules: {
            ...tsRecommendedRules,
        },
    },
    eslintConfigPrettier,
];
