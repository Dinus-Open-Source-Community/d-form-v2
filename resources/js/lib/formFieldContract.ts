/**
 * Kontrak lintas sisi untuk blok field builder.
 *
 * Ini satu-satunya sumber daftar display-only di frontend. `fieldMapping`
 * (builder) dan `formFillFields` (fill helpers) wajib mengimpor dari sini agar
 * daftar tidak bercabang. Sisi server memakai
 * `RulesBuilder::DISPLAY_ONLY_BUILDER_TYPES`; kesepakatan kedua sisi diuji
 * lewat `tests/fixtures/form-field-contract.json`.
 */

/** Builder types that render content only and never produce an answer key. */
export const FORM_FILL_DISPLAY_ONLY_TYPES: readonly string[] = ['heading', 'paragraph', 'divider', 'banner', 'image'];

/** Set form of the same list for builder mappers. */
export const DISPLAY_ONLY_BUILDER_TYPES: ReadonlySet<string> = new Set(FORM_FILL_DISPLAY_ONLY_TYPES);

/** True when a builder type must not produce an answer key. */
export function isDisplayOnlyBuilderType(type: unknown): boolean {
    return typeof type === 'string' && DISPLAY_ONLY_BUILDER_TYPES.has(type);
}
