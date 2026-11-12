import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const routesSourcePath = fileURLToPath(new URL('../routes.ts', import.meta.url));
const source = readFileSync(routesSourcePath, 'utf8');

/** Buang komentar tanpa merusak `//` di dalam string (mis. URL). */
const stripComments = (value: string): string => {
    let result = '';
    let quote: string | null = null;
    let escaped = false;
    let index = 0;

    while (index < value.length) {
        const char = value[index];
        const next = value[index + 1];

        if (quote !== null) {
            result += char;

            if (escaped) {
                escaped = false;
            } else if (char === '\\') {
                escaped = true;
            } else if (char === quote) {
                quote = null;
            }

            index += 1;
            continue;
        }

        if (char === "'" || char === '"' || char === '`') {
            quote = char;
            result += char;
            index += 1;
            continue;
        }

        if (char === '/' && next === '*') {
            index += 2;

            while (index < value.length && !(value[index] === '*' && value[index + 1] === '/')) {
                index += 1;
            }

            index += 2;
            continue;
        }

        if (char === '/' && next === '/') {
            while (index < value.length && value[index] !== '\n') {
                index += 1;
            }

            continue;
        }

        result += char;
        index += 1;
    }

    return result;
};

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
});
