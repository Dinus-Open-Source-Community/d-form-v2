import { isBarePackageSpecifier, isLibRuntimeImportAllowed, normalizeRepoPath } from './frontendFolderBoundaries.js';

/** ESLint rule ID for the runtime-package boundary. */
export const LIB_RUNTIME_PACKAGES_RULE_ID = 'dform/lib-runtime-packages';

/**
 * Reject runtime-package imports from `lib/`; non-literal dynamic imports are
 * intentionally left for manual review.
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
                if (node.specifiers.length > 0 && node.specifiers.every((specifier) => specifier.exportKind === 'type'))
                    return;
                checkSource(node);
            },
            ExportAllDeclaration(node) {
                if (node.exportKind === 'type') return;
                checkSource(node);
            },
            ImportExpression(node) {
                if (node.source.type !== 'Literal' || typeof node.source.value !== 'string') return;
                checkSource(node);
            },
        };
    },
};
