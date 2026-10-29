import { basename, extname } from 'node:path';
import { test as base, type TestInfo, type WorkerInfo } from '@playwright/test';
import { runArtisanCommand, runArtisanJson, runArtisanWithInput } from './artisan';
import { assertAdminState } from './authGuard';
import { startWorkerHeartbeat, type IHeartbeatHandle } from './heartbeat';
import { createOwnerIdentity, createRunId, type IOwnerIdentity } from './marker';

/** Satu field yang dibuat fixture. */
export interface IFixtureFieldRequest {
    label: string;
    name: string;
    input_type: string;
    required: boolean;
}

/** Satu form beserta field dan jumlah submission yang diminta. */
export interface IFixtureFormRequest {
    title: string;
    fields: IFixtureFieldRequest[];
    submissions: number;
}

/** Payload `php artisan e2e:fixture` (dikirim sebagai JSON lewat stdin). */
export interface IFixtureRequest {
    marker: string;
    slug: string;
    title: string;
    forms: IFixtureFormRequest[];
}

/** ID satu form hasil fixture. */
export interface IFixtureFormResult {
    formId: string;
    fieldIds: string[];
    submissionIds: string[];
}

/** Hasil command fixture: pemilik, event, dan form yang dibuat. */
export interface IFixtureCommandResult {
    userId: string;
    eventId: string;
    eventSlug: string;
    forms: IFixtureFormResult[];
}

/** Identitas pemilik yang dipakai satu percobaan test. */
export interface IFixtureOwner extends IOwnerIdentity {
    userId: string;
    eventId: string;
    eventSlug: string;
}

/** Hasil fixture form: form utama dan seluruh form yang diminta. */
export interface IFixtureForms {
    primary: IFixtureFormResult;
    forms: IFixtureFormResult[];
}

/** Option per-spec untuk mengatur jumlah form/field/submission yang dibuat. */
export interface IHarnessOptions {
    fixtureFormCount: number;
    fixtureFieldCount: number;
    fixtureSubmissionCount: number;
}

/** Fixture yang tersedia bagi spec lewat `test` dari modul ini. */
export interface IHarnessFixtures {
    fixtureOwner: IFixtureOwner;
    fixtureEvent: IFixtureOwner;
    fixtureForm: IFixtureForms;
    fixtureSubmissions: { formId: string; submissionIds: string[] };
}

/** Fixture worker-scoped: heartbeat auto agar crash terdeteksi teardown/sweep. */
interface IHarnessWorkerFixtures {
    workerHeartbeat: void;
}

/** Fixture auto yang memastikan storageState admin valid sebelum test terautentikasi. */
interface IHarnessAutoFixtures {
    adminStateGuard: void;
}

/** Hasil `e2e:count-rows`: jumlah per tabel dan totalnya. */
export interface IFixtureRowCounts {
    counts: Record<string, number>;
    count: number;
}

const runId = process.env.E2E_RUN_ID ?? createRunId();

/** Identitas pemilik dari lokasi test; retry menghasilkan marker baru sepenuhnya. */
export function ownerIdentityForTest(testInfo: TestInfo): IOwnerIdentity {
    return createOwnerIdentity(runId, {
        project: testInfo.project.name,
        specSlug: basename(testInfo.file, extname(testInfo.file)),
        testSlug: testInfo.title,
        retry: testInfo.retry,
    });
}

/** Payload fixture default: `formCount` form, masing-masing `fieldCount` field dan `submissionCount` submission. */
export function defaultFixtureRequest(
    identity: IOwnerIdentity,
    formCount: number,
    fieldCount: number,
    submissionCount: number,
): IFixtureRequest {
    const fields = Array.from({ length: Math.max(1, fieldCount) }, (_, index: number): IFixtureFieldRequest => ({
        label: `Field ${index + 1}`,
        name: `field_${index + 1}`,
        input_type: 'input',
        required: index === 0,
    }));

    return {
        marker: identity.marker,
        slug: identity.slug,
        title: `E2E fixture ${identity.ownerKey}`,
        forms: Array.from({ length: Math.max(1, formCount) }, (_, index: number): IFixtureFormRequest => ({
            title: `Form ${index + 1}`,
            fields,
            submissions: Math.max(0, submissionCount),
        })),
    };
}

/** Buat pemilik + event (tanpa form) lewat satu command artisan; idempoten untuk marker yang sama. */
export function createOwnerFixture(identity: IOwnerIdentity): IFixtureOwner {
    const request: IFixtureRequest = {
        marker: identity.marker,
        slug: identity.slug,
        title: `E2E fixture ${identity.ownerKey}`,
        forms: [],
    };
    const result = runFixtureCommand(request);

    return {
        ...identity,
        userId: result.userId,
        eventId: result.eventId,
        eventSlug: result.eventSlug,
    };
}

/** Buat form/field/submission pada event milik `owner`; event dipakai ulang lewat slug yang sama. */
export function createFormsFixture(owner: IFixtureOwner, request: IFixtureRequest): IFixtureFormResult[] {
    const result = runFixtureCommand({
        ...request,
        marker: owner.marker,
        slug: owner.slug,
        title: `E2E fixture ${owner.ownerKey}`,
    });

    return result.forms;
}

/** Hapus seluruh baris milik satu marker secara exact; idempoten dan aman dipanggil berulang. */
export function cleanupOwnerFixture(identity: IOwnerIdentity): void {
    runArtisanCommand(['e2e:cleanup', `--marker=${identity.marker}`, `--slug=${identity.slug}`, '--json']);
}

/** Jalankan `e2e:fixture` dan parse JSON hasilnya; satu-satunya jalur tulis fixture. */
export function runFixtureCommand(request: IFixtureRequest): IFixtureCommandResult {
    const output = runArtisanWithInput(['e2e:fixture', `--marker=${request.marker}`], JSON.stringify(request));

    return JSON.parse(output) as IFixtureCommandResult;
}

/** Jumlah baris ber-marker per tabel; dipakai assertion sebelum/sesudah mutasi. */
export function countFixtureRows(marker: string): IFixtureRowCounts {
    return runArtisanJson<IFixtureRowCounts>(['e2e:count-rows', `--marker=${marker}`]);
}

/** Test harness: fixture data ber-marker, heartbeat worker, dan guard storageState admin. */
export const test = base.extend<IHarnessOptions & IHarnessFixtures & IHarnessAutoFixtures, IHarnessWorkerFixtures>({
    fixtureFormCount: [1, { option: true }],
    fixtureFieldCount: [1, { option: true }],
    fixtureSubmissionCount: [0, { option: true }],

    workerHeartbeat: [
        async ({}, use: () => Promise<void>, workerInfo: WorkerInfo): Promise<void> => {
            const heartbeat: IHeartbeatHandle = startWorkerHeartbeat({
                project: workerInfo.project.name,
                workerIndex: workerInfo.workerIndex,
                runId,
            });

            try {
                await use();
            } finally {
                heartbeat.stop();
            }
        },
        { scope: 'worker', auto: true },
    ],

    adminStateGuard: [
        async ({}, use: () => Promise<void>, testInfo: TestInfo): Promise<void> => {
            const storageState = testInfo.project.use.storageState;

            // Hanya project terautentikasi (storageState berupa path) yang wajib punya state valid.
            if (typeof storageState === 'string') {
                assertAdminState(storageState);
            }

            await use();
        },
        { auto: true },
    ],

    fixtureOwner: async ({}, use: (owner: IFixtureOwner) => Promise<void>, testInfo: TestInfo): Promise<void> => {
        const identity = ownerIdentityForTest(testInfo);
        const owner = createOwnerFixture(identity);

        try {
            await use(owner);
        } finally {
            cleanupOwnerFixture(identity);
        }
    },

    fixtureEvent: async ({ fixtureOwner }, use: (owner: IFixtureOwner) => Promise<void>): Promise<void> => {
        await use(fixtureOwner);
    },

    fixtureForm: async (
        { fixtureOwner, fixtureFormCount, fixtureFieldCount, fixtureSubmissionCount },
        use: (forms: IFixtureForms) => Promise<void>,
        testInfo: TestInfo,
    ): Promise<void> => {
        const identity = ownerIdentityForTest(testInfo);
        const forms = createFormsFixture(
            fixtureOwner,
            defaultFixtureRequest(identity, fixtureFormCount, fixtureFieldCount, fixtureSubmissionCount),
        );
        const primary = forms[0];

        if (primary === undefined) {
            throw new Error('e2e:fixture returned no form.');
        }

        await use({ primary, forms });
    },

    fixtureSubmissions: async (
        { fixtureForm },
        use: (submissions: { formId: string; submissionIds: string[] }) => Promise<void>,
    ): Promise<void> => {
        await use({ formId: fixtureForm.primary.formId, submissionIds: fixtureForm.primary.submissionIds });
    },
});

export { expect } from '@playwright/test';
