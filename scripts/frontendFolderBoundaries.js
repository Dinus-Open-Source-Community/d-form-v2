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
