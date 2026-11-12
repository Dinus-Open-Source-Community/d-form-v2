import { spawnSync } from 'node:child_process';
import { existsSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * Menjamin artifact Wayfinder (`resources/js/routes`, `resources/js/actions`)
 * tersedia sebelum `typecheck`, `typecheck:unit`, dan `test:unit` berjalan.
 *
 * Kedua folder gitignored (`.gitignore:47-49`), sehingga clean checkout tidak
 * punya artifact dan tsc/Vitest gagal pada import `@/routes/**` atau `@/actions/**`.
 * Script ini idempoten: bila artifact sudah ada (mis. CI sudah men-generate),
 * tidak ada perintah yang dijalankan.
 *
 * Override untuk lingkungan container:
 * `WAYFINDER_COMMAND='podman exec -w /app <container> php artisan wayfinder:generate'`
 */

const REPO_ROOT = fileURLToPath(new URL('../..', import.meta.url));
const GENERATED_DIRECTORIES = ['resources/js/routes', 'resources/js/actions'];
const DEFAULT_COMMAND = 'php artisan wayfinder:generate';

function isPopulated(directory) {
    if (!existsSync(directory)) return false;
    return readdirSync(directory).length > 0;
}

function artifactsPresent() {
    return GENERATED_DIRECTORIES.every((directory) => isPopulated(join(REPO_ROOT, directory)));
}

function printRecoveryHint() {
    console.error(
        [
            '[wayfinder] Artifact Wayfinder belum tersedia dan generate otomatis gagal.',
            '  Jalankan generate lalu ulangi perintah:',
            '    php artisan wayfinder:generate',
            '  Dari container (PHP tidak di host):',
            '    podman exec -w /app <container> php artisan wayfinder:generate',
            '  Atau override perintah untuk npm script:',
            "    WAYFINDER_COMMAND='podman exec -w /app <container> php artisan wayfinder:generate' npm run typecheck",
            '  Dokumentasi: README.md bagian "Gate frontend (typecheck & unit test)".',
        ].join('\n')
    );
}

if (artifactsPresent()) {
    process.exit(0);
}

const command = process.env.WAYFINDER_COMMAND ?? DEFAULT_COMMAND;

console.log(`[wayfinder] artifact belum ada; menjalankan: ${command}`);
const result = spawnSync(command, [], { cwd: REPO_ROOT, shell: true, stdio: 'inherit' });

if (result.error) {
    console.error(`[wayfinder] gagal menjalankan "${command}": ${result.error.message}`);
    printRecoveryHint();
    process.exit(1);
}

if (result.status !== 0) {
    console.error(`[wayfinder] perintah generate gagal (exit ${result.status ?? 'unknown'}).`);
    printRecoveryHint();
    process.exit(1);
}

if (!artifactsPresent()) {
    console.error('[wayfinder] generate selesai tetapi resources/js/routes atau resources/js/actions masih kosong.');
    printRecoveryHint();
    process.exit(1);
}
