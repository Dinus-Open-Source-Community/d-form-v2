import { readdirSync, readFileSync } from 'node:fs';
import { join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

/**
 * Architecture guard for DOM globals inside `components/ui/**`.
 *
 * `components/ui/` must stay library output (AGENTS.md: dilarang diedit
 * langsung), so the four non-shadcn legacy components below keep their own
 * `document`/`window` access instead of being rewritten through the browser
 * adapter. The allowlist is explicit and frozen: any *new* file under
 * `components/ui/**` that touches a DOM global fails this guard, and a
 * stale entry (file removed or no longer using the globals) fails too.
 *
 * Rejected alternative: a `components/core/` wrapper that re-implements the
 * select/sidebar behaviour on top of `@/adapters/browser`. It duplicates three
 * working components, keeps the legacy copies alive anyway, and adds bundle
 * weight without removing the DOM access it claims to hide.
 */
const UI_ROOT = fileURLToPath(new URL('../components/ui', import.meta.url));

/** Legacy library-ish files allowed to keep DOM globals; path relative to `components/ui/`. */
const ALLOWED_DOM_GLOBAL_FILES: ReadonlySet<string> = new Set([
    // Measures trigger width with a resize listener; Reka UI popover positioning.
    'searchable-select/SearchableSelect.vue',
    // Persists the sidebar cookie (`document.cookie`) for shadcn sidebar state.
    'sidebar/SidebarProvider.vue',
    // Same resize listener as SearchableSelect.
    'simple-select/SimpleSelect.vue',
    // Same resize listener as SearchableSelect.
    'styled-select/StyledSelect.vue',
]);

/** `document.x`, `window.x`, `fetch(…)`, `localStorage[…]`, etc.; identifier must be a whole word. */
const DOM_GLOBAL_PATTERN = /\b(?:document|window|localStorage|sessionStorage|navigator|fetch)\s*(?:\.|\(|\[)/;

const SOURCE_FILE = /\.(ts|tsx|mts|vue|js)$/;

function collectUiFiles(directory: string): string[] {
    return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const fullPath = join(directory, entry.name);
        if (entry.isDirectory()) {
            return entry.name === '__tests__' ? [] : collectUiFiles(fullPath);
        }
        return SOURCE_FILE.test(entry.name) ? [fullPath] : [];
    });
}

function toUiPath(file: string): string {
    return relative(UI_ROOT, file).split('\\').join('/');
}

function usesDomGlobal(file: string): boolean {
    return DOM_GLOBAL_PATTERN.test(readFileSync(file, 'utf8'));
}

describe('components/ui DOM boundary', () => {
    it('only allows DOM globals in the documented legacy allowlist', () => {
        const offenders = collectUiFiles(UI_ROOT)
            .filter(usesDomGlobal)
            .map(toUiPath)
            .filter((path) => !ALLOWED_DOM_GLOBAL_FILES.has(path))
            .sort();

        expect(
            offenders,
            `DOM global baru di components/ui/** dilarang. Pakai @/adapters/browser atau wrapper di components/core/:\n${offenders.join('\n')}`
        ).toEqual([]);
    });

    it('keeps the allowlist honest: every entry still exists and still uses a DOM global', () => {
        const files = new Set(collectUiFiles(UI_ROOT).map(toUiPath));

        for (const allowed of ALLOWED_DOM_GLOBAL_FILES) {
            expect(files.has(allowed), `${allowed} ada di allowlist tetapi tidak ditemukan`).toBe(true);
            expect(
                usesDomGlobal(join(UI_ROOT, allowed)),
                `${allowed} tidak lagi memakai DOM global; hapus dari allowlist`
            ).toBe(true);
        }
    });
});
