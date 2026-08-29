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
            // Pemindahan fokus otomatis, tree ARIA, dan video legacy perlu
            // perubahan UI terpisah. Pertahankan perilaku sekarang, tetapi
            // tampilkan temuan agar dapat ditutup saat scope UI dibuka lagi.
            'jsx-a11y/no-autofocus': 'warn',
            'jsx-a11y/media-has-caption': 'warn',
            'jsx-a11y/role-has-required-aria-props': 'warn',
            'jsx-a11y/role-supports-aria-props': 'warn',
        },
    },
    prettier,
);
