/**
 * AGENTS.md: factory dan guard murni yang terikat siklus hidup form builder boleh
 * co-locate di `resources/js/components/modules/builder/`. Daftar ini adalah sumber
 * tunggal untuk aturan ESLint hooks dan harus identik dengan set di
 * `resources/js/__tests__/importBoundaries.test.ts`.
 */
export const BUILDER_COLOCATED_MODULES = [
    'autosaveGuard',
    'dirtyFields',
    'fieldMapping',
    'formBanner',
    'formBuilderFieldFactory',
    'formBuilderPalette',
    'optionImage',
];

/**
 * Paket murni (nilai/fungsi tanpa DOM maupun efek samping) yang sah dipakai `lib/`.
 * Tambahkan hanya bila paketnya deterministik dan tidak menyentuh runtime UI.
 */
export const LIB_PURE_PACKAGE_ALLOWLIST = ['@internationalized/date', 'clsx', 'tailwind-merge'];

/**
 * Pengecualian sementara per file untuk paket runtime yang belum bisa dipindah dari `lib/`.
 * Setiap entri wajib menyebut alasan + tiket lanjutan agar bisa dihapus saat migrasinya mendarat.
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
 * Normalisasi path absolut ESLint/OS menjadi path repo relatif (`resources/js/...`).
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
 * `true` bila specifier adalah paket (bukan relative path dan bukan alias `@/`).
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
 * `true` bila impor paket runtime diizinkan untuk file `lib/` tertentu.
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
