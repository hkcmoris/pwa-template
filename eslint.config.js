const js = require('@eslint/js');
const tsPlugin = require('@typescript-eslint/eslint-plugin');
const tsParser = require('@typescript-eslint/parser');
const globals = require('globals');
const eslintConfigPrettier = require('eslint-config-prettier');

module.exports = [
    {
        ignores: ['dist/**', 'public/assets/**'],
    },
    js.configs.recommended,
    {
        files: ['src/**/*.{ts,tsx}'],
        languageOptions: {
            parser: tsParser,
            parserOptions: {
                ecmaVersion: 'latest',
                sourceType: 'module',
            },
            globals: globals.browser,
        },
        plugins: {
            '@typescript-eslint': tsPlugin,
        },
        rules: {
            ...tsPlugin.configs.recommended.rules,
            'no-undef': 'off',
        },
    },
    eslintConfigPrettier,
];
