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
