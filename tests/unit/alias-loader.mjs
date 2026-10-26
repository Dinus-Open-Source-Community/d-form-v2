import { registerHooks } from 'node:module';
import { existsSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
import path from 'node:path';

const projectRoot = path.resolve(import.meta.dirname, '../..');

/** Petakan `@/x` (alias Vite) ke file nyata di `resources/js`, termasuk ekstensi implisit. */
function resolveAlias(specifier) {
    const base = path.join(projectRoot, 'resources/js', specifier.slice(2));
    const candidates = [base, `${base}.ts`, `${base}.vue`, path.join(base, 'index.ts')];

    return candidates.find((candidate) => existsSync(candidate)) ?? base;
}

// `node --test` tidak tahu alias `@/` milik Vite; hook resolve ini hanya menambahkan
// pemetaan tersebut supaya modul aplikasi bisa diimpor apa adanya tanpa bundler.
registerHooks({
    resolve(specifier, context, nextResolve) {
        if (specifier.startsWith('@/')) {
            return nextResolve(pathToFileURL(resolveAlias(specifier)).href, context);
        }

        return nextResolve(specifier, context);
    },
});
