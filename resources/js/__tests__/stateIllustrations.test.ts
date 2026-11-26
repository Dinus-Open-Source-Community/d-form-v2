import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const ROOT = fileURLToPath(new URL('../../..', import.meta.url));

function readAsset(name: string): string {
    return readFileSync(`${ROOT}public/illustrations/${name}.svg`, 'utf8');
}

describe('state illustrations', () => {
    it.each(['state-error', 'state-empty'])('%s.svg adalah SVG vektor di bawah 30 kB', (name: string) => {
        const svg = readAsset(name);

        expect(svg.startsWith('<svg')).toBe(true);
        expect(svg).toContain('viewBox');
        expect(Buffer.byteLength(svg)).toBeLessThan(30 * 1024);
    });

    it('kedua ilustrasi memakai aksen proyek', () => {
        for (const name of ['state-error', 'state-empty']) {
            expect(readAsset(name)).toContain('#2a81dc');
        }
    });
});
