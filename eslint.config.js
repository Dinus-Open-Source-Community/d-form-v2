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

    ...tseslint.configs.recommended,
    ...pluginVue.configs['flat/essential'],
    ...vueTsConfig(),
    skipFormatting,

    {
        rules: {
            'vue/multi-word-component-names': 'off', // Biar nggak error kalau nama file cuma satu kata
            '@typescript-eslint/no-unused-vars': 'warn',
        },
    }
);
