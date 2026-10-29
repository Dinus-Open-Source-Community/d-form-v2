import { mkdirSync, rmSync, utimesSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { expect, test, countFixtureRows, cleanupOwnerFixture, runFixtureCommand } from './support/fixtures';
import { assertAdminState } from './support/authGuard';
import { assertEnvironment } from './support/environment';
import { heartbeatDirectory, isHeartbeatLive } from './support/heartbeat';
import { startRequestLog } from './support/network';
import {
    createMarker,
    createOwnerId,
    createOwnerIdentity,
    createOwnerKey,
    createRunId,
    markerToSlug,
    ownerEmail,
} from './support/marker';
import { runArtisanCommand } from './support/artisan';

/** Jalankan callback tanpa `E2E_RUN_ID` agar format timestamp bisa diuji deterministik. */
function withoutRunIdEnv<T>(run: () => T): T {
    const previous = process.env.E2E_RUN_ID;
    delete process.env.E2E_RUN_ID;

    try {
        return run();
    } finally {
        if (previous !== undefined) {
            process.env.E2E_RUN_ID = previous;
        }
    }
}

/** Backdate semua baris satu run agar sweep menganggapnya tua tanpa menunggu 24 jam. */
function backdateRun(runId: string): void {
    const prefix = `e2e+${runId}+`;
    const slugPrefix = markerToSlug(prefix);
    const code = [
        `$prefix='${prefix}';$slug='${slugPrefix}';`,
        `\\Illuminate\\Support\\Facades\\DB::table('users')->where('email','like',$prefix.'%')->update(['created_at'=>now()->subHours(30)]);`,
        `\\Illuminate\\Support\\Facades\\DB::table('events')->where('slug','like',$slug.'%')->update(['created_at'=>now()->subHours(30)]);`,
        `\\Illuminate\\Support\\Facades\\DB::table('forms')->where('title','like','% ['.$prefix.'%')->update(['created_at'=>now()->subHours(30)]);`,
    ].join('');

    runArtisanCommand(['tinker', '--execute', code]);
}

test.describe('marker identity', () => {
    test('H-marker-format: runId, ownerKey, marker, dan slug mengikuti satu format kanonik', (): void => {
        const runId = withoutRunIdEnv((): string => createRunId(new Date('2026-01-02T03:04:05Z'), 'abcdef'));
        const ownerKey = createOwnerKey('chromium-desktop.spec.title.r0');
        const marker = createMarker(runId, ownerKey);

        expect(runId).toBe('20260102T030405-abcdef');
        expect(runId).toHaveLength(22);
        expect(ownerKey).toMatch(/^o[0-9a-f]{12}$/);
        expect(marker).toHaveLength(40);
        expect(marker).toBe(`e2e+${runId}+${ownerKey}`);
        expect(markerToSlug(marker)).toBe(marker.toLowerCase().replaceAll('+', '-'));
        expect(ownerEmail(marker)).toBe(`${marker}@e2e.invalid`);
    });

    test('retry menghasilkan ownerKey dan marker yang sama sekali berbeda', (): void => {
        const first = createOwnerKey(
            createOwnerId({ project: 'chromium-desktop', specSlug: 'a.spec', testSlug: 'judul', retry: 0 }),
        );
        const retry = createOwnerKey(
            createOwnerId({ project: 'chromium-desktop', specSlug: 'a.spec', testSlug: 'judul', retry: 1 }),
        );

        expect(first).not.toBe(retry);
    });

    test('ownerId disanitasi ke [a-z0-9-.] dan menyertakan retry', (): void => {
        const ownerId = createOwnerId({
            project: 'Chromium Desktop',
            specSlug: 'Auth/Login.spec',
            testSlug: 'Login  Gagal!',
            retry: 2,
        });

        expect(ownerId).toBe('chromium-desktop.auth-login.spec.login-gagal.r2');
        expect(ownerId).toMatch(/^[a-z0-9-.]+$/);
    });
});

test.describe('admin state guard', () => {
    test('state tanpa cookie sesi melempar error, bukan skip', (): void => {
        const statePath = join(tmpdir(), `e2e-cookie-less-state-${process.pid}-${Date.now()}.json`);
        const cookieName = 'e2e-session-cookie-marker';
        const previous = process.env.E2E_SESSION_COOKIE;

        writeFileSync(statePath, JSON.stringify({ cookies: [], origins: [] }), 'utf8');
        process.env.E2E_SESSION_COOKIE = cookieName;

        try {
            expect((): void => {
                assertAdminState(statePath);
            }).toThrow(cookieName);
            expect((): void => {
                assertAdminState(statePath);
            }).toThrow('test:e2e:setup');
        } finally {
            if (previous === undefined) {
                delete process.env.E2E_SESSION_COOKIE;
            } else {
                process.env.E2E_SESSION_COOKIE = previous;
            }
        }
    });
});

test.describe('environment fail-fast', () => {
    test('H-env-base-url: base URL tak terjangkau menyebut E2E_BASE_URL lebih dulu, bukan E2E_ARTISAN', async (): Promise<void> => {
        await expect(
            assertEnvironment({ env: { E2E_BASE_URL: 'http://127.0.0.1:1', E2E_ARTISAN: 'tidak-ada-command-xyz' } }),
        ).rejects.toThrow(/E2E_BASE_URL/);
        await expect(
            assertEnvironment({ env: { E2E_BASE_URL: 'http://127.0.0.1:1', E2E_ARTISAN: 'tidak-ada-command-xyz' } }),
        ).rejects.not.toThrow(/E2E_ARTISAN/);
    });

    test('H-env-artisan: token pertama E2E_ARTISAN yang tidak ada menyebut E2E_ARTISAN dan command lengkap', async (): Promise<void> => {
        await expect(
            assertEnvironment({
                env: {
                    E2E_BASE_URL: 'http://example.test',
                    E2E_ARTISAN: 'definitely-not-a-command artisan',
                    E2E_ADMIN_EMAIL: 'admin@example.test',
                    E2E_ADMIN_PASSWORD: 'secret',
                },
                probeBaseUrl: false,
            }),
        ).rejects.toThrow(/E2E_ARTISAN.*definitely-not-a-command artisan/);
    });

    test('kredensial kosong dua-duanya disebut bersama', async (): Promise<void> => {
        await expect(
            assertEnvironment({
                env: {
                    E2E_BASE_URL: 'http://example.test',
                    E2E_ARTISAN: 'node',
                    E2E_ADMIN_EMAIL: '',
                    E2E_ADMIN_PASSWORD: '',
                },
                probeBaseUrl: false,
            }),
        ).rejects.toThrow('E2E_ADMIN_EMAIL and E2E_ADMIN_PASSWORD');
    });
});

test.describe('fixture lifecycle', () => {
    test.use({ fixtureFormCount: 2, fixtureFieldCount: 2, fixtureSubmissionCount: 1 });

    test('H-fixture-ownership: user/event/form/field/answer/attendance membawa marker kanonik', async ({
        fixtureOwner,
        fixtureForm,
    }): Promise<void> => {
        const marker = fixtureOwner.marker;
        const counts = countFixtureRows(marker);

        expect(fixtureForm.forms).toHaveLength(2);
        expect(fixtureForm.primary.fieldIds).toHaveLength(2);
        expect(fixtureForm.primary.submissionIds).toHaveLength(1);
        expect(counts.counts.users).toBe(1);
        expect(counts.counts.events).toBe(1);
        expect(counts.counts.forms).toBe(2);
        expect(counts.counts.form_fields).toBe(4);
        expect(counts.counts.form_answers).toBe(2);
        expect(counts.counts.event_attendances).toBe(2);
    });

    test('H-marker-format: cleanup menghapus event melalui markerToSlug, bukan marker mentah', async ({
        fixtureOwner,
    }): Promise<void> => {
        const marker = fixtureOwner.marker;

        cleanupOwnerFixture(fixtureOwner);

        expect(countFixtureRows(marker).count).toBe(0);
        expect(fixtureOwner.slug).toBe(markerToSlug(marker));
    });

    test('H-teardown-scope: teardown r0 tidak menghapus baris retry r1', (): void => {
        const runId = process.env.E2E_RUN_ID ?? createRunId();
        const baseParts = { project: 'chromium-desktop', specSlug: 'scope.spec', testSlug: 'retry', retry: 0 };
        const first = createOwnerIdentity(runId, baseParts);
        const retry = createOwnerIdentity(runId, { ...baseParts, retry: 1 });

        runFixtureCommand({ marker: retry.marker, slug: retry.slug, title: 'Retry', forms: [] });
        runFixtureCommand({ marker: first.marker, slug: first.slug, title: 'First', forms: [] });

        try {
            cleanupOwnerFixture(first);
            expect(countFixtureRows(first.marker).count).toBe(0);
            expect(countFixtureRows(retry.marker).counts.events).toBe(1);
        } finally {
            cleanupOwnerFixture(retry);
        }
    });

    test('H-fixture-partial-failure: kegagalan di form_fields tidak meninggalkan baris induk', (): void => {
        const runId = process.env.E2E_RUN_ID ?? createRunId();
        const identity = createOwnerIdentity(runId, {
            project: 'chromium-desktop',
            specSlug: 'harness.spec',
            testSlug: 'partial-failure',
            retry: 0,
        });

        expect((): void => {
            runFixtureCommand({
                marker: identity.marker,
                slug: identity.slug,
                title: 'Partial',
                forms: [
                    {
                        title: 'Broken form',
                        fields: [{ label: 'Field', name: 'field_1', input_type: 'not-a-valid-enum', required: true }],
                        submissions: 0,
                    },
                ],
            });
        }).toThrow();

        expect(countFixtureRows(identity.marker).count).toBe(0);
    });

    test('H-cleanup-failure: kegagalan hapus menyebut tabel, exit non-zero, dan sisanya dilaporkan check:row-ownership', (): void => {
        const runId = '20000101T000007-abcdef';
        const identity = createOwnerIdentity(runId, {
            project: 'chromium-desktop',
            specSlug: 'harness.spec',
            testSlug: 'cleanup-failure',
            retry: 0,
        });
        const blockerSlug = `blocker-${identity.ownerKey}`;
        const tinker = (code: string): string => runArtisanCommand(['tinker', '--execute', code]);
        const created = runFixtureCommand({
            marker: identity.marker,
            slug: identity.slug,
            title: 'Cleanup failure',
            forms: [],
        });

        // Event di luar scope marker menahan `DELETE users` lewat FK restrictOnDelete, sehingga
        // cleanup harus gagal keras (bukan diam-diam) dan sisa barisnya kelihatan.
        tinker(
            "\\Illuminate\\Support\\Facades\\DB::table('events')->insert(["
                + "'id'=>(string) \\Illuminate\\Support\\Str::orderedUuid(),"
                + "'title'=>'E2E blocker',"
                + `'slug'=>'${blockerSlug}',`
                + "'description'=>'blocker','start_date'=>now()->toDateString(),"
                + "'end_date'=>now()->addDay()->toDateString(),"
                + "'registration_start'=>now()->subDay(),'registration_end'=>now()->addDay(),"
                + "'location'=>'blocker','quota'=>1,'registered_count'=>0,"
                + "'banner'=>'events/banners/placeholder.jpg','price'=>0,'session'=>'general',"
                + "'status'=>'draft','category'=>'etc',"
                + `'created_by'=>'${created.userId}',`
                + "'created_at'=>now(),'updated_at'=>now()]);",
        );

        try {
            expect((): void => cleanupOwnerFixture(identity)).toThrow(/e2e cleanup failed on table users/);

            expect((): string => runArtisanCommand(['check:row-ownership', `--run-id=${runId}`, '--json'])).toThrow(
                /"leftoverTotal":2/,
            );
        } finally {
            tinker(`\\Illuminate\\Support\\Facades\\DB::table('events')->where('slug','${blockerSlug}')->delete();`);
            cleanupOwnerFixture(identity);
        }
    });

    test('H-cleanup-slug-prefix: cleanup menolak slug-prefix yang bukan turunan marker-prefix', (): void => {
        const runId = '20000101T000004-abcdef';
        const identity = createOwnerIdentity(runId, {
            project: 'chromium-desktop',
            specSlug: 'harness.spec',
            testSlug: 'slug-guard',
            retry: 0,
        });
        // Prefix sah secara format (22 karakter) tetapi milik run lain: satu-satunya yang menahannya
        // adalah pemeriksaan kesetaraan dengan markerToSlug(marker-prefix).
        const foreignSlugPrefix = 'e2e-20000101t000005-abcdef-';

        runFixtureCommand({ marker: identity.marker, slug: identity.slug, title: 'Slug guard', forms: [] });

        try {
            expect((): string =>
                runArtisanCommand([
                    'e2e:cleanup',
                    `--marker-prefix=e2e+${runId}+`,
                    `--slug-prefix=${foreignSlugPrefix}`,
                    '--json',
                ]),
            ).toThrow(/slug prefix must equal markerToSlug/);

            expect(countFixtureRows(identity.marker).counts.events).toBe(1);
        } finally {
            cleanupOwnerFixture(identity);
        }
    });
});

test.describe('heartbeat lifecycle', () => {
    test('H-heartbeat-live: file baru dengan pid proses test runner dianggap hidup', (): void => {
        const runId = '20000101T000000-abcdef';
        const directory = heartbeatDirectory(runId);
        const file = join(directory, `harness.${process.pid}.json`);

        mkdirSync(directory, { recursive: true });
        writeFileSync(file, JSON.stringify({ pid: process.pid, runId, startedAt: new Date().toISOString() }));

        try {
            expect(isHeartbeatLive(file)).toBe(true);
        } finally {
            rmSync(file, { force: true });
            rmSync(directory, { recursive: true, force: true });
        }
    });

    test('H-heartbeat-stale: pid mati atau mtime 12 jam dianggap basi', (): void => {
        const runId = '20000101T000001-abcdef';
        const directory = heartbeatDirectory(runId);
        const deadFile = join(directory, 'dead.json');
        const oldFile = join(directory, 'old.json');

        mkdirSync(directory, { recursive: true });
        writeFileSync(deadFile, JSON.stringify({ pid: 999_999, runId, startedAt: new Date().toISOString() }));
        writeFileSync(oldFile, JSON.stringify({ pid: process.pid, runId, startedAt: new Date().toISOString() }));

        const old = new Date(Date.now() - 12 * 60 * 60 * 1000);
        utimesSync(oldFile, old, old);

        try {
            expect(isHeartbeatLive(deadFile)).toBe(false);
            expect(isHeartbeatLive(oldFile)).toBe(false);
        } finally {
            rmSync(directory, { recursive: true, force: true });
        }
    });

    test('H-sweep-abandoned: sweep menghapus run tanpa heartbeat yang lebih tua dari grace', (): void => {
        const runId = '20000101T000002-abcdef';
        const ownerKey = 'o000000000001';
        const marker = createMarker(runId, ownerKey);

        runFixtureCommand({ marker, slug: markerToSlug(marker), title: 'Abandoned', forms: [] });
        backdateRun(runId);

        runArtisanCommand(['e2e:sweep', '--grace-hours=24', '--budget=120', '--json']);

        expect(countFixtureRows(marker).count).toBe(0);
    });

    test('H-sweep-domain: sweep tidak menyapu run yang email pemiliknya bukan domain e2e.invalid', (): void => {
        const runId = '20000101T000003-abcdef';
        const identity = createOwnerIdentity(runId, {
            project: 'chromium-desktop',
            specSlug: 'harness.spec',
            testSlug: 'sweep-domain',
            retry: 0,
        });
        const alienEmail = `${identity.marker}@example.com`;
        const tinker = (code: string): string => runArtisanCommand(['tinker', '--execute', code]);
        const countAlien = `echo \\Illuminate\\Support\\Facades\\DB::table('users')->where('email','${alienEmail}')->count();`;

        runFixtureCommand({ marker: identity.marker, slug: identity.slug, title: 'Alien domain', forms: [] });

        try {
            // Domain diganti ke example.com: run ini tidak lagi memenuhi syarat pemilik e2e.invalid,
            // jadi sweep tidak boleh menyentuhnya meski slug/markernya sah dan barisnya sudah tua.
            tinker(
                `\\Illuminate\\Support\\Facades\\DB::table('users')->where('email','${identity.marker}@e2e.invalid')`
                    + `->update(['email'=>'${alienEmail}','created_at'=>now()->subHours(30)]);`,
            );

            runArtisanCommand(['e2e:sweep', '--grace-hours=24', '--budget=120', '--json']);

            expect(tinker(countAlien)).toBe('1');
            expect(countFixtureRows(identity.marker).counts.events).toBe(1);
        } finally {
            tinker(`\\Illuminate\\Support\\Facades\\DB::table('users')->where('email','${alienEmail}')->delete();`);
            cleanupOwnerFixture(identity);
        }
    });
});

test.describe('observation helpers', () => {
    test('H-observe: request dashboard tercatat beserta status respons', async ({ page }): Promise<void> => {
        const log = startRequestLog(page);

        try {
            await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
            expect(log.requestCount('GET', '/dashboard')).toBeGreaterThan(0);
            expect(log.responseStatuses('/dashboard')).toContain(200);
        } finally {
            log.stop();
        }
    });
});

test.describe('harness isolation', () => {
    test('H-harness-isolation: dua fixture berbeda tidak berbagi marker', ({ fixtureOwner }, testInfo): void => {
        const other = createOwnerIdentity(process.env.E2E_RUN_ID ?? createRunId(), {
            project: testInfo.project.name,
            specSlug: 'harness.spec',
            testSlug: 'another-test',
            retry: 0,
        });

        expect(other.marker).not.toBe(fixtureOwner.marker);
        expect(countFixtureRows(other.marker).count).toBe(0);
    });

    test('H-harness-parallel: teardown satu test tidak menghapus baris test lain di run yang sama', ({
        fixtureOwner,
    }): void => {
        const sibling = createOwnerIdentity(process.env.E2E_RUN_ID ?? createRunId(), {
            project: 'chromium-desktop',
            specSlug: 'harness.spec',
            testSlug: 'parallel-sibling',
            retry: 0,
        });

        runFixtureCommand({ marker: sibling.marker, slug: sibling.slug, title: 'Parallel sibling', forms: [] });

        try {
            cleanupOwnerFixture(fixtureOwner);

            expect(countFixtureRows(sibling.marker).counts.events).toBe(1);
        } finally {
            cleanupOwnerFixture(sibling);
        }
    });
});
