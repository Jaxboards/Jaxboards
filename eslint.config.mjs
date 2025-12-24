import eslint from '@eslint/js';
import eslintPluginPrettierRecommended from 'eslint-plugin-prettier/recommended';
import { defineConfig } from 'eslint/config';
import globals from 'globals';
import tseslint from 'typescript-eslint';

// mimic CommonJS variables -- not needed if using CommonJS

export default defineConfig(
    eslint.configs.recommended,
    tseslint.configs.recommended,
    // tseslint.configs.strict,
    // tseslint.configs.stylistic,
    // tseslint.configs.recommendedTypeChecked,
    eslintPluginPrettierRecommended,
    {
        files: ['**/*.ts'],
        languageOptions: {
            globals: {
                ...globals.browser,
            },
            parserOptions: {
                projectService: true,
            },
        },
        rules: {
            // Disables the rule preventing modifying properties on objects passed in
            'no-param-reassign': [2, { props: false }],
            'prettier/prettier': ['error', { singleQuote: true }],
            'import/extensions': 0,
        },

        settings: {
            'import/resolver': {
                node: {
                    extensions: ['.js', '.jsx', '.ts', '.tsx'],
                },
            },
        },
    },
);
