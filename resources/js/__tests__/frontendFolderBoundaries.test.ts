import { readFileSync, readdirSync, unlinkSync, writeFileSync } from 'node:fs';
import { join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { ESLint } from 'eslint';
import type { Linter } from 'eslint';
import { describe, expect, it } from 'vitest';
import {
    LIB_PURE_PACKAGE_ALLOWLIST,
    LIB_RUNTIME_IMPORT_EXCEPTIONS,
} from '../../../scripts/frontendFolderBoundaries.js';

/**
 * Guard `AUDIT-P1-012-F1`: `resources/js/lib/**` tidak boleh mengimpor paket
 * runtime. Test ini menjalankan ESLint proyek lewat `ESLint#lintText`/`lintFiles`
 * (config `eslint.config.js` yang sama dengan `npm run lint`), sehingga verdict
 * guard Vitest identik dengan job blocking — satu implementasi, bukan parser regex.
 */

const REPO_ROOT = fileURLToPath(new URL('../../..', import.meta.url));
const JS_ROOT = join(REPO_ROOT, 'resources/js');
const LIB_ROOT = join(JS_ROOT, 'lib');
const RULE_ID = 'dform/lib-runtime-packages';
const SOURCE_FILE = /\.(ts|tsx|mts|js)$/;
const eslint = new ESLint({ cwd: REPO_ROOT });

function policyViolations(messages: readonly Linter.LintMessage[]): Linter.LintMessage[] {
    return messages.filter((message) => message.ruleId === RULE_ID);
}

async function lintSource(source: string, relativePath: string): Promise<Linter.LintMessage[]> {
    const [result] = await eslint.lintText(source, { filePath: join(JS_ROOT, relativePath) });
    return policyViolations(result?.messages ?? []);
}

function collectLibFiles(directory: string): string[] {
    return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const fullPath = join(directory, entry.name);
        if (entry.isDirectory()) {
            return entry.name === '__tests__' ? [] : collectLibFiles(fullPath);
        }
        return SOURCE_FILE.test(entry.name) ? [fullPath] : [];
    });
}

describe('lib/ runtime package guard (project ESLint config)', () => {
    it('keeps every real lib/** file clean under the blocking config', async () => {
        const files = collectLibFiles(LIB_ROOT);
        const results = await eslint.lintFiles(files);
        const violations = results.flatMap((result) =>
            policyViolations(result.messages).map(
                (message) => `${relative(REPO_ROOT, result.filePath)}: ${message.message}`
            )
        );

        expect(violations, violations.join('\n')).toEqual([]);
    }, 30_000);

    it.each([
        ['named type import', `import type { Foo } from 'vue-sonner';`],
        ['inline type import', `import { type Foo } from 'vue-sonner';`],
        ['type-only re-export', `export type { Foo } from 'vue-sonner';`],
        ['inline type re-export', `export { type Foo } from 'vue-sonner';`],
        ['commented-out import', `// import { toast } from 'vue-sonner';`],
        ['relative import', `import { isRecord } from './isRecord';`],
        ['alias import', `import { isRecord } from '@/lib/isRecord';`],
        ['pure allowlisted package', `import { clsx } from 'clsx';`],
    ])('passes %s', async (_label: string, source: string) => {
        expect(await lintSource(source, 'lib/formFixture.ts')).toEqual([]);
    });

    it.each([
        ['named runtime import', `import { toast } from 'vue-sonner';`],
        ['side-effect import', `import 'vue-sonner';`],
        ['literal dynamic import', `const load = (): Promise<unknown> => import('vue-sonner');`],
        ['value re-export', `export { toast } from 'vue-sonner';`],
        ['star re-export', `export * from 'vue-sonner';`],
    ])('reports %s with file + specifier', async (_label: string, source: string) => {
        const violations = await lintSource(source, 'lib/formFixture.ts');

        expect(violations).toHaveLength(1);
        expect(violations[0]?.message).toContain('vue-sonner');
        expect(violations[0]?.message).toContain('resources/js/lib/formFixture.ts');
    });

    it('keeps documented per-file exceptions working and scoped to that file', async () => {
        const exception = LIB_RUNTIME_IMPORT_EXCEPTIONS[0];
        const source = `import { toast } from '${exception.packages[0]}';`;

        expect(await lintSource(source, exception.file.replace('resources/js/', ''))).toEqual([]);
        expect(await lintSource(source, 'lib/otherToast.ts')).toHaveLength(1);
        expect(LIB_PURE_PACKAGE_ALLOWLIST).toContain('clsx');
    });

    it('mutation probe: a real temp lib file importing a runtime package fails through the blocking config', async () => {
        const probePath = join(LIB_ROOT, '__libRuntimePackagesProbe.ts');
        const source = "import { toast } from 'vue-sonner';\n\nexport const probe = (): unknown => toast;\n";

        writeFileSync(probePath, source);
        try {
            expect(readFileSync(probePath, 'utf8')).toBe(source);
            const [result] = await eslint.lintFiles([probePath]);
            const violations = policyViolations(result?.messages ?? []);

            expect(violations).toHaveLength(1);
            expect(violations[0]?.message).toContain('vue-sonner');
            expect(violations[0]?.message).toContain('resources/js/lib/__libRuntimePackagesProbe.ts');
        } finally {
            unlinkSync(probePath);
        }
    }, 30_000);
});
