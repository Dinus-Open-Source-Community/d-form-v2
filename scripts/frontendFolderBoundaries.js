/** Builder modules allowed to co-locate; mirrored by the import-boundary tests. */
export const BUILDER_COLOCATED_MODULES = [
    'autosaveGuard',
    'dirtyFields',
    'fieldMapping',
    'formBanner',
    'formBuilderFieldFactory',
    'formBuilderPalette',
    'optionImage',
];

/** Deterministic packages allowed in `lib/`. */
export const LIB_PURE_PACKAGE_ALLOWLIST = ['@internationalized/date', 'clsx', 'tailwind-merge'];

/**
 * Temporary per-file runtime exceptions; each entry carries its migration reason.
 * @type {Array<{file: string, packages: string[], reason: string}>}
 */
export const LIB_RUNTIME_IMPORT_EXCEPTIONS = [
    {
        file: 'resources/js/lib/autosaveFailureToast.ts',
        packages: ['vue-sonner'],
        reason: 'AUDIT-P1-012-F1: efek toast masih di lib; pindahkan ke hooks/useAutosaveFailureToast saat seam toast mendarat.',
    },
    {
        file: 'resources/js/lib/icons.ts',
        packages: ['lucide-vue-next'],
        reason: 'Katalog ikon merender komponen Vue; pindahkan ke components/core saat seam ikon dimigrasikan.',
    },
    {
        file: 'resources/js/lib/qrScanUi.ts',
        packages: ['lucide-vue-next'],
        reason: 'Ikon status scan adalah komponen Vue; pindahkan bersama follow-up UI scanner.',
    },
];

/**
 * @param {string} filePath
 * @returns {string}
 */
export function normalizeRepoPath(filePath) {
    const normalized = String(filePath).split('\\').join('/');
    const marker = '/resources/js/';
    const markerIndex = normalized.indexOf(marker);
    if (markerIndex !== -1) return normalized.slice(markerIndex + 1);
    if (normalized.startsWith('resources/js/')) return normalized;
    return `resources/js/${normalized.replace(/^\.?\//, '')}`;
}

/**
 * @param {string} specifier
 * @returns {boolean}
 */
export function isBarePackageSpecifier(specifier) {
    return !specifier.startsWith('.') && !specifier.startsWith('@/');
}

/**
 * @param {string} specifier
 * @param {string} packageName
 * @returns {boolean}
 */
function packageMatches(specifier, packageName) {
    return specifier === packageName || specifier.startsWith(`${packageName}/`);
}

/**
 * @param {{
 *   file: string,
 *   specifier: string,
 *   allowlist?: string[],
 *   exceptions?: Array<{file: string, packages: string[]}>,
 * }} input
 * @returns {boolean}
 */
export function isLibRuntimeImportAllowed({
    file,
    specifier,
    allowlist = LIB_PURE_PACKAGE_ALLOWLIST,
    exceptions = LIB_RUNTIME_IMPORT_EXCEPTIONS,
}) {
    if (!isBarePackageSpecifier(specifier)) return true;
    if (allowlist.some((packageName) => packageMatches(specifier, packageName))) return true;

    const repoPath = normalizeRepoPath(file);
    return exceptions.some(
        (entry) =>
            normalizeRepoPath(entry.file) === repoPath &&
            entry.packages.some((packageName) => packageMatches(specifier, packageName))
    );
}
