import pluginVue from 'eslint-plugin-vue';
import tseslint from 'typescript-eslint';
import vueTsConfig from '@vue/eslint-config-typescript';
import skipFormatting from '@vue/eslint-config-prettier/skip-formatting';
import { BUILDER_COLOCATED_MODULES } from './scripts/frontendFolderBoundaries.js';
import { LIB_RUNTIME_PACKAGES_RULE_ID, libRuntimePackageRule } from './scripts/libRuntimePackageRule.js';

/**
 * FE-AUDIT-015: impor per folder mengikuti tabel folder AGENTS.md.
 * `axios` tetap dilarang di semua folder non-adapter (FE-AUDIT-004), jadi
 * setiap override `no-restricted-imports` mengulang path yang sama.
 */
const AXIOS_IMPORT_PATH = {
    name: 'axios',
    message: 'Gunakan @/adapters/http/httpClient, bukan axios langsung.',
};

/**
 * @param {Array<Record<string, unknown>>} patterns
 * @returns {import('eslint').Linter.RuleEntry}
 */
function restrictedImports(patterns) {
    return ['error', { paths: [AXIOS_IMPORT_PATH], patterns }];
}

/**
 * `lib/` hanya pure function: impor paket runtime dilarang. Satu implementasi
 * rule ada di `scripts/libRuntimePackageRule.js`, dipakai config ini dan test
 * guard Vitest agar kedua jalur memberi verdict yang sama.
 */

const LIB_FORBIDDEN = [
    '@/components',
    '@/components/**',
    '@/pages',
    '@/pages/**',
    '@/layouts',
    '@/layouts/**',
    '@/hooks',
    '@/hooks/**',
    '@/adapters',
    '@/adapters/**',
    '@/utils',
    '@/utils/**',
    '@/plugins',
    '@/plugins/**',
    '@/directives',
    '@/directives/**',
    // `@/constants` sengaja diizinkan: nilai statis tanpa behavior (lihat
    // docs/rules/front-end-structure.md: lib -> types, constants).
];
const TYPES_FORBIDDEN = [...LIB_FORBIDDEN, '@/lib', '@/lib/**'];
const HOOKS_LAYOUT_FORBIDDEN = [
    '@/pages',
    '@/pages/**',
    '@/layouts',
    '@/layouts/**',
    '@/components/ui',
    '@/components/ui/**',
];
// AGENTS.md mengizinkan factory/guard form-builder co-locate di
// components/modules/builder/ dan diimpor hook pemilik siklusnya.
// Daftar sumber: scripts/frontendFolderBoundaries.js (sinkron dengan
// resources/js/__tests__/importBoundaries.test.ts).
const HOOKS_MODULES_PATTERN = `^@/components/modules/(?!builder/(?:${BUILDER_COLOCATED_MODULES.join('|')})$)`;
const CORE_FORBIDDEN = [
    '@/components/modules',
    '@/components/modules/**',
    '@/pages',
    '@/pages/**',
    '@/layouts',
    '@/layouts/**',
    '@/hooks',
    '@/hooks/**',
];
const ADAPTERS_FORBIDDEN = ['@/pages', '@/pages/**', '@/layouts', '@/layouts/**', '@/components', '@/components/**'];

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
        ignores: ['resources/js/adapters/**', 'resources/js/components/ui/**', 'resources/js/wayfinder/**'],
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
        name: 'app/layer-lib',
        files: ['resources/js/lib/**/*.{ts,vue,js}'],
        rules: {
            // lib/ hanya pure function: tidak boleh menyentuh layer UI/state/adapter.
            'no-restricted-imports': restrictedImports([
                {
                    group: LIB_FORBIDDEN,
                    message: 'lib/ hanya pure function; impor komponen, page, layout, hook, atau adapter dilarang.',
                },
            ]),
            // Vue adalah runtime library: hanya type-only import yang diizinkan.
            '@typescript-eslint/no-restricted-imports': [
                'error',
                {
                    patterns: [
                        {
                            group: ['vue'],
                            message: 'lib/ hanya pure function; gunakan `import type` untuk tipe Vue.',
                            allowTypeImports: true,
                        },
                    ],
                },
            ],
        },
    },

    {
        name: 'app/layer-lib-runtime-packages',
        files: ['resources/js/lib/**/*.{ts,vue,js}'],
        ignores: ['resources/js/lib/**/__tests__/**'],
        plugins: {
            dform: {
                meta: { name: 'dform' },
                rules: { 'lib-runtime-packages': libRuntimePackageRule },
            },
        },
        rules: {
            [LIB_RUNTIME_PACKAGES_RULE_ID]: 'error',
        },
    },

    {
        name: 'app/layer-types',
        files: ['resources/js/types/**/*.{ts,vue,js}'],
        rules: {
            // types/ hanya kontrak tipe lintas file.
            'no-restricted-imports': restrictedImports([
                {
                    group: TYPES_FORBIDDEN,
                    message: 'types/ hanya kontrak tipe; impor layer runtime dilarang.',
                },
            ]),
            // Vue adalah runtime library: hanya type-only import yang diizinkan.
            '@typescript-eslint/no-restricted-imports': [
                'error',
                {
                    patterns: [
                        {
                            group: ['vue'],
                            message: 'types/ hanya kontrak tipe; gunakan `import type` untuk tipe Vue.',
                            allowTypeImports: true,
                        },
                    ],
                },
            ],
        },
    },

    {
        name: 'app/layer-hooks',
        files: ['resources/js/hooks/**/*.{ts,vue,js}'],
        rules: {
            'no-restricted-imports': restrictedImports([
                {
                    group: HOOKS_LAYOUT_FORBIDDEN,
                    message: 'hooks/ tidak boleh mengimpor page, layout, atau components/ui.',
                },
                {
                    regex: HOOKS_MODULES_PATTERN,
                    message:
                        'hooks/ hanya boleh mengimpor factory/guard form-builder dari daftar AGENTS.md di scripts/frontendFolderBoundaries.js.',
                },
            ]),
        },
    },

    {
        name: 'app/layer-components-core',
        files: ['resources/js/components/core/**/*.{ts,vue,js}'],
        rules: {
            'no-restricted-imports': restrictedImports([
                {
                    group: CORE_FORBIDDEN,
                    message: 'components/core tidak boleh mengimpor module, page, layout, atau hook.',
                },
            ]),
        },
    },

    {
        name: 'app/layer-adapters',
        files: ['resources/js/adapters/**/*.{ts,vue,js}'],
        rules: {
            // Adapter hanya boleh menyentuh browser/third-party; axios tetap boleh
            // di adapters/ karena merupakan seam HTTP resmi.
            'no-restricted-imports': [
                'error',
                {
                    patterns: [
                        {
                            group: ADAPTERS_FORBIDDEN,
                            message: 'adapters/ tidak boleh mengimpor page, layout, atau component.',
                        },
                    ],
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
