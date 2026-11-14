import { createHash, randomBytes } from 'node:crypto';

export interface IOwnerIdentity {
    ownerId: string;
    ownerKey: string;
    runId: string;
    marker: string;
    slug: string;
}

export interface IOwnerIdParts {
    project: string;
    specSlug: string;
    testSlug: string;
    retry: number;
}

const RUN_ID_PATTERN = /^\d{8}T\d{6}-[0-9a-f]{6}$/;
const OWNER_KEY_PATTERN = /^o[0-9a-f]{12}$/;
const MARKER_PATTERN = /^e2e\+[0-9A-Za-z-]{1,22}\+o[0-9a-f]{12}$/;

/**
 * Bangun `runId` 22 karakter: `E2E_RUN_ID` bila diisi, selain itu `<YYYYMMDDTHHMMSS>-<6 hex>`.
 * Timestamp memakai UTC tanpa akhiran `Z` agar panjang 22 dan marker 40 tetap terjaga.
 */
export function createRunId(now: Date = new Date(), randomHex: string = randomBytes(3).toString('hex')): string {
    const fromEnv = (process.env.E2E_RUN_ID ?? '').trim();

    if (fromEnv !== '') {
        if (!RUN_ID_PATTERN.test(fromEnv)) {
            throw new Error(
                `E2E_RUN_ID must match ${RUN_ID_PATTERN.source} (22 characters, e.g. 20260102T030405-abcdef).`
            );
        }

        return fromEnv;
    }

    const stamp = now
        .toISOString()
        .replace(/[-:]/g, '')
        .replace(/\.\d{3}Z$/, '')
        .replace(/Z$/, '');

    return `${stamp}-${randomHex}`;
}

export function slugifySegment(value: string): string {
    return (
        value
            .toLowerCase()
            .replace(/[^a-z0-9-.]+/g, '-')
            .replace(/-{2,}/g, '-')
            .replace(/^[-.]+|[-.]+$/g, '') || 'test'
    );
}

export function createOwnerId(parts: IOwnerIdParts): string {
    const retry = Number.isFinite(parts.retry) && parts.retry > 0 ? Math.trunc(parts.retry) : 0;

    return [parts.project, parts.specSlug, parts.testSlug].map(slugifySegment).join('.') + `.r${retry}`;
}

/** Hash the owner ID into the canonical fixture key. */
export function createOwnerKey(ownerId: string): string {
    return 'o' + createHash('sha256').update(ownerId).digest('hex').slice(0, 12);
}

/** Marker kanonik 40 karakter: `e2e+<runId>+<ownerKey>`. Nilai inilah yang diterima `--marker`. */
export function createMarker(runId: string, ownerKey: string): string {
    const marker = `e2e+${runId}+${ownerKey}`;

    if (marker.length !== 40 || !MARKER_PATTERN.test(marker)) {
        throw new Error(`Invalid e2e marker "${marker}"; expected e2e+<runId>+o<12 hex> and 40 characters.`);
    }

    return marker;
}

/** Build the identity shared by fixture creation and teardown. */
export function createOwnerIdentity(runId: string, parts: IOwnerIdParts): IOwnerIdentity {
    const ownerId = createOwnerId(parts);
    const ownerKey = createOwnerKey(ownerId);
    const marker = createMarker(runId, ownerKey);

    return { ownerId, ownerKey, runId, marker, slug: markerToSlug(marker) };
}

export function markerToSlug(marker: string): string {
    return marker.toLowerCase().replaceAll('+', '-');
}

export function ownerEmail(marker: string): string {
    return `${marker}@e2e.invalid`;
}

export function runIdFromMarker(marker: string): string | null {
    if (!MARKER_PATTERN.test(marker)) {
        return null;
    }

    return marker.split('+')[1] ?? null;
}

export function runMarkerPrefix(runId: string): string {
    return `e2e+${runId}+`;
}

export function runSlugPrefix(runId: string): string {
    return markerToSlug(runMarkerPrefix(runId));
}

/** Pastikan `ownerKey` yang datang dari file/env tetap berbentuk kanonik. */
export function isOwnerKey(value: string): boolean {
    return OWNER_KEY_PATTERN.test(value);
}
