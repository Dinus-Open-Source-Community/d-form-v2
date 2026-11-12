import { isBarePackageSpecifier, isLibRuntimeImportAllowed, normalizeRepoPath } from './frontendFolderBoundaries.js';

/** ID rule ESLint untuk guard `lib/` bebas paket runtime (dipakai config + test). */
export const LIB_RUNTIME_PACKAGES_RULE_ID = 'dform/lib-runtime-packages';

/**
 * `lib/` hanya pure function: impor paket runtime (bukan alias `@/` dan bukan
 * relative) dilarang. Allowlist paket murni dan pengecualian sementara per file
 * berada di `scripts/frontendFolderBoundaries.js`; pesan menyebut file + specifier.
 *
 * Batasan yang diketahui (L5): dynamic import dengan specifier non-literal
 * (mis. `import(packageName)`) tidak dapat di-resolve statis dan tidak dilaporkan.
 * Pemakaian tersebut tetap harus ditinjau manual saat review.
 *
 * @type {import('eslint').Rule.RuleModule}
 */
export const libRuntimePackageRule = {
    meta: {
        type: 'problem',
        docs: { description: 'lib/ hanya pure function: impor paket runtime dilarang.' },
        schema: [],
        messages: {
            forbidden:
                'lib/ hanya pure function: impor paket runtime "{{specifier}}" dilarang di {{file}}. Pindahkan efeknya ke adapters/ atau hooks/.',
        },
    },
    create(context) {
        const report = (node, specifier) => {
            context.report({
                node,
                messageId: 'forbidden',
                data: { specifier, file: normalizeRepoPath(context.filename) },
            });
        };
        const checkSource = (node) => {
            const specifier = node.source?.value;
            if (typeof specifier !== 'string') return;
            if (!isBarePackageSpecifier(specifier)) return;
            if (isLibRuntimeImportAllowed({ file: context.filename, specifier })) return;
            report(node.source, specifier);
        };

        return {
            ImportDeclaration(node) {
                if (node.importKind === 'type') return;
                if (node.specifiers.length > 0 && node.specifiers.every((specifier) => specifier.importKind === 'type'))
                    return;
                checkSource(node);
            },
            ExportNamedDeclaration(node) {
                if (node.exportKind === 'type') return;
                // `export { type Foo } from 'pkg'`: semua specifier type-only.
                if (node.specifiers.length > 0 && node.specifiers.every((specifier) => specifier.exportKind === 'type'))
                    return;
                checkSource(node);
            },
            ExportAllDeclaration(node) {
                if (node.exportKind === 'type') return;
                checkSource(node);
            },
            ImportExpression(node) {
                // Literal saja yang bisa diputuskan statis; lihat batasan L5 di atas.
                if (node.source.type !== 'Literal' || typeof node.source.value !== 'string') return;
                checkSource(node);
            },
        };
    },
};
