import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const routesSourcePath = fileURLToPath(new URL('../routes.ts', import.meta.url));
const source = readFileSync(routesSourcePath, 'utf8');

const stripComments = (value: string): string => value.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/.*$/gm, '');

const sourceWithoutComments = stripComments(source);

const stringLiteralPattern = /(['"])((?:\\.|(?!\1).)*)\1/g;
const templateLiteralPattern = /`((?:\\.|[^`\\])*)`/g;

const manualPathLiterals = [
    ...[...sourceWithoutComments.matchAll(stringLiteralPattern)].map((match) => match[2]),
    ...[...sourceWithoutComments.matchAll(templateLiteralPattern)].map((match) => match[1]),
].filter((literal) => literal.startsWith('/'));

// Broadcast dorman tidak di-generate saat FEATURE_BROADCAST=false.
const ALLOWED_MANUAL_PATHS = ['/admin/broadcasts'];

describe('lib/routes.ts source guard', () => {
    it('imports route helpers from the generated @/routes source', () => {
        expect(sourceWithoutComments).toMatch(/from ['"]@\/routes['"]/);
        expect(sourceWithoutComments).toMatch(/from ['"]@\/routes\/[^'"]+['"]/);
    });

    it('has no manual path literal outside the broadcast allowlist', () => {
        const unexpectedLiterals = [...new Set(manualPathLiterals)].filter(
            (literal) => !ALLOWED_MANUAL_PATHS.includes(literal)
        );

        expect(unexpectedLiterals, `literal path manual baru: ${unexpectedLiterals.join(', ')}`).toEqual([]);
    });

    it('does not rebuild a manual admin base constant', () => {
        expect(source).not.toMatch(/const\s+ADMIN_BASE\s*=\s*['"]\/admin['"]/);
    });
});
