import eslintPluginPrettierRecommended from 'eslint-plugin-prettier/recommended';
import globals from 'globals';
import path from 'path';
import { FlatCompat } from '@eslint/eslintrc';
import { fileURLToPath } from 'url';

// mimic CommonJS variables -- not needed if using CommonJS
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const compat = new FlatCompat({
    baseDirectory: __dirname,
});

export default [
    ...compat.extends('airbnb-base'),
    eslintPluginPrettierRecommended,
    {
        files: ['**/*.js'],
        languageOptions: {
            globals: {
                ...globals.browser,
            },
        },
        rules: {
            // Disables the rule preventing modifying properties on objects passed in
            'no-param-reassign': [2, { props: false }],
            'prettier/prettier': ['error', { singleQuote: true }],
        },
    },
];
