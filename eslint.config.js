import pluginVue from 'eslint-plugin-vue';
import tseslint from 'typescript-eslint';
import vueTsConfig from '@vue/eslint-config-typescript';
import skipFormatting from '@vue/eslint-config-prettier/skip-formatting';

export default tseslint.config(
    {
        name: 'app/files-to-lint',
        files: ['**/*.{ts,mts,tsx,vue}'],
    },

    {
        name: 'app/files-to-ignore',
        ignores: ['**/dist/**', '**/dist-ssr/**', '**/coverage/**', 'vendor/**', 'public/**'],
    },

    {
        name: 'app/linter-options',
        linterOptions: {
            // No inline suppression at all: both used and stale disables are rejected.
            noInlineConfig: true,
            reportUnusedDisableDirectives: 'error',
        },
    },

    ...tseslint.configs.recommended,
    ...pluginVue.configs['flat/essential'],
    ...vueTsConfig(),
    skipFormatting,

    {
        name: 'app/adapter-boundary',
        files: ['resources/js/**/*.{ts,vue,js}'],
        ignores: [
            'resources/js/adapters/**',
            'resources/js/components/ui/**',
            'resources/js/wayfinder/**',
        ],
        rules: {
            // FE-AUDIT-004: HTTP dan browser global hanya lewat adapters/.
            'no-restricted-imports': [
                'error',
                {
                    paths: [
                        {
                            name: 'axios',
                            message: 'Gunakan @/adapters/http/httpClient, bukan axios langsung.',
                        },
                    ],
                },
            ],
            'no-restricted-globals': [
                'error',
                { name: 'document', message: 'Gunakan @/adapters/browser/*.' },
                { name: 'window', message: 'Gunakan @/adapters/browser/*.' },
                { name: 'localStorage', message: 'Gunakan @/adapters/browser/storage.' },
                { name: 'sessionStorage', message: 'Gunakan @/adapters/browser/storage.' },
                { name: 'navigator', message: 'Gunakan @/adapters/browser/environment.' },
                { name: 'fetch', message: 'Gunakan @/adapters/http/httpClient.' },
            ],
            'no-restricted-syntax': [
                'error',
                {
                    selector:
                        "MemberExpression[object.name='globalThis'][property.name=/^(document|window|localStorage|sessionStorage|navigator|fetch)$/]",
                    message: 'Gunakan @/adapters/browser/* atau @/adapters/http/*.',
                },
            ],
        },
    },

    {
        rules: {
            'vue/multi-word-component-names': 'off', // Biar nggak error kalau nama file cuma satu kata
            '@typescript-eslint/no-unused-vars': 'warn',
            // AGENTS.md: no non-null assertion, no suppression comments, no prop mutation.
            '@typescript-eslint/no-non-null-assertion': 'error',
            '@typescript-eslint/ban-ts-comment': [
                'error',
                {
                    'ts-expect-error': true,
                    'ts-ignore': true,
                    'ts-nocheck': true,
                    'ts-check': false,
                },
            ],
            'vue/no-mutating-props': 'error',
            // The TS rule above ignores template expressions; this covers Vue templates.
            'vue/no-restricted-syntax': [
                'error',
                {
                    selector: 'TSNonNullExpression',
                    message: 'Non-null assertion is forbidden; use an explicit guard or fallback.',
                },
            ],
        },
    }
);
