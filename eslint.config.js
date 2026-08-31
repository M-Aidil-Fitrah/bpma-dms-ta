import js from '@eslint/js';
import prettier from 'eslint-config-prettier';
import jsxA11y from 'eslint-plugin-jsx-a11y';
import reactHooks from 'eslint-plugin-react-hooks';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    {
        ignores: ['public/build/**', 'resources/js/types/generated.d.ts'],
    },
    js.configs.recommended,
    ...tseslint.configs.recommended,
    {
        files: ['resources/js/**/*.{ts,tsx}'],
        languageOptions: {
            parserOptions: {
                ecmaFeatures: { jsx: true },
            },
        },
        plugins: {
            'jsx-a11y': jsxA11y,
            'react-hooks': reactHooks,
        },
        rules: {
            ...jsxA11y.flatConfigs.recommended.rules,
            'react-hooks/rules-of-hooks': 'error',
            'react-hooks/exhaustive-deps': 'warn',
            // Aturan jsx-a11y dijalankan pada tingkat `error` bawaannya. Pemakaian
            // `autoFocus` yang memang disengaja (layar auth, field pertama
            // dialog) dan media unggahan tanpa trek teks ditandai per-baris
            // dengan `eslint-disable-next-line` beserta alasannya.
        },
    },
    prettier,
);
