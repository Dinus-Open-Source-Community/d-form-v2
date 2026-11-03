import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import { gzipSync } from 'node:zlib';

// Budget dari AGENTS.md §Code splitting. Diukur pada hasil `npm run build`.
const BUILD_DIR = 'public/build';
const ENTRY_SOURCE = 'resources/js/app.js';
const ENTRY_BUDGET_BYTES = 350 * 1024;
const CHUNK_BUDGET_BYTES = 200 * 1024;

const manifest = JSON.parse(readFileSync(join(BUILD_DIR, 'manifest.json'), 'utf8'));
const entry = manifest[ENTRY_SOURCE];

if (entry === undefined || !entry.file.endsWith('.js')) {
    console.error(`bundle budget: no JS entry for ${ENTRY_SOURCE} in ${BUILD_DIR}/manifest.json`);
    process.exit(1);
}

/** Ukuran gzip satu file hasil build; ukuran mentah tidak dipakai karena budget dinyatakan dalam gzip. */
function gzipBytes(file) {
    return gzipSync(readFileSync(join(BUILD_DIR, file))).length;
}

const chunks = readdirSync(join(BUILD_DIR, 'assets'))
    .filter((file) => file.endsWith('.js'))
    .map((file) => ({ file: `assets/${file}`, bytes: gzipBytes(`assets/${file}`) }))
    .sort((left, right) => right.bytes - left.bytes);

const entryBytes = gzipBytes(entry.file);
const overflow = chunks.filter((chunk) => chunk.file !== entry.file && chunk.bytes > CHUNK_BUDGET_BYTES);

const format = (bytes) => `${(bytes / 1024).toFixed(1)} kB`;

console.log(`entry ${entry.file}: ${format(entryBytes)} gzip (budget ${format(ENTRY_BUDGET_BYTES)})`);
console.log('largest non-entry chunks:');
for (const chunk of chunks.filter((candidate) => candidate.file !== entry.file).slice(0, 5)) {
    console.log(`  ${format(chunk.bytes).padStart(9)}  ${chunk.file}`);
}

const failures = [];

if (entryBytes > ENTRY_BUDGET_BYTES) {
    failures.push(`entry ${entry.file} is ${format(entryBytes)}, over ${format(ENTRY_BUDGET_BYTES)}`);
}

for (const chunk of overflow) {
    failures.push(`${chunk.file} is ${format(chunk.bytes)}, over ${format(CHUNK_BUDGET_BYTES)}`);
}

if (failures.length > 0) {
    console.error(`bundle budget failed:\n${failures.map((line) => `  - ${line}`).join('\n')}`);
    process.exit(1);
}

console.log(
    `bundle budget ok: entry <= ${format(ENTRY_BUDGET_BYTES)}, ${chunks.length - 1} chunks <= ${format(CHUNK_BUDGET_BYTES)}`
);
