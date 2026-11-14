import { basename, extname } from 'node:path';
import { test as base, type TestInfo, type WorkerInfo } from '@playwright/test';
import { runArtisanCommand, runArtisanJson, runArtisanWithInput } from './artisan';
import { assertAdminState } from './authGuard';
import { startWorkerHeartbeat, type IHeartbeatHandle } from './heartbeat';
import { createOwnerIdentity, createRunId, type IOwnerIdentity } from './marker';

export interface IFixtureFieldRequest {
    label: string;
    name: string;
    input_type: string;
    required: boolean;
}

export interface IFixtureFormRequest {
    title: string;
    fields: IFixtureFieldRequest[];
    submissions: number;
}

export interface IFixtureRequest {
    marker: string;
    slug: string;
    title: string;
    forms: IFixtureFormRequest[];
    /** Email user yang sudah ada; bila diisi, kepemilikan fixture memakai user itu tanpa membuat marker user. */
    ownerEmail?: string;
}

export interface IFixtureFormResult {
    formId: string;
    fieldIds: string[];
    submissionIds: string[];
}

export interface IFixtureCommandResult {
    userId: string;
    eventId: string;
    eventSlug: string;
    forms: IFixtureFormResult[];
}

export interface IFixtureOwner extends IOwnerIdentity {
    userId: string;
    eventId: string;
    eventSlug: string;
}

export interface IFixtureForms {
    primary: IFixtureFormResult;
    forms: IFixtureFormResult[];
}

export interface IHarnessOptions {
    fixtureFormCount: number;
    fixtureFieldCount: number;
    fixtureSubmissionCount: number;
}

export interface IHarnessFixtures {
    fixtureOwner: IFixtureOwner;
    fixtureEvent: IFixtureOwner;
    fixtureForm: IFixtureForms;
    fixtureSubmissions: { formId: string; submissionIds: string[] };
}

interface IHarnessWorkerFixtures {
    workerHeartbeat: void;
}

interface IHarnessAutoFixtures {
    adminStateGuard: void;
}

export interface IFixtureRowCounts {
    counts: Record<string, number>;
    count: number;
}

const runId = process.env.E2E_RUN_ID ?? createRunId();

export function ownerIdentityForTest(testInfo: TestInfo): IOwnerIdentity {
    return createOwnerIdentity(runId, {
        project: testInfo.project.name,
        specSlug: basename(testInfo.file, extname(testInfo.file)),
        testSlug: testInfo.title,
        retry: testInfo.retry,
    });
}

export function defaultFixtureRequest(
    identity: IOwnerIdentity,
    formCount: number,
    fieldCount: number,
    submissionCount: number
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

/**
 * Buat pemilik + event (tanpa form) lewat satu command artisan; idempoten untuk marker yang sama.
 * `ownerEmail` membuat event milik user yang sudah ada tanpa membuat marker user.
 */
export function createOwnerFixture(identity: IOwnerIdentity, ownerEmail?: string): IFixtureOwner {
    const request: IFixtureRequest = {
        marker: identity.marker,
        slug: identity.slug,
        title: `E2E fixture ${identity.ownerKey}`,
        forms: [],
    };

    if (ownerEmail !== undefined) {
        request.ownerEmail = ownerEmail;
    }

    const result = runFixtureCommand(request);

    return {
        ...identity,
        userId: result.userId,
        eventId: result.eventId,
        eventSlug: result.eventSlug,
    };
}

export function createFormsFixture(owner: IFixtureOwner, request: IFixtureRequest): IFixtureFormResult[] {
    const result = runFixtureCommand({
        ...request,
        marker: owner.marker,
        slug: owner.slug,
        title: `E2E fixture ${owner.ownerKey}`,
    });

    return result.forms;
}

/** Remove only rows owned by this marker; safe to repeat during teardown. */
export function cleanupOwnerFixture(identity: IOwnerIdentity): void {
    runArtisanCommand(['e2e:cleanup', `--marker=${identity.marker}`, `--slug=${identity.slug}`, '--json']);
}

/** Run the single fixture-writing command and parse its JSON result. */
export function runFixtureCommand(request: IFixtureRequest): IFixtureCommandResult {
    const output = runArtisanWithInput(['e2e:fixture', `--marker=${request.marker}`], JSON.stringify(request));

    return JSON.parse(output) as IFixtureCommandResult;
}

export function countFixtureRows(marker: string): IFixtureRowCounts {
    return runArtisanJson<IFixtureRowCounts>(['e2e:count-rows', `--marker=${marker}`]);
}

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

            // Only authenticated projects have a storage state to validate.
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
        testInfo: TestInfo
    ): Promise<void> => {
        const identity = ownerIdentityForTest(testInfo);
        const forms = createFormsFixture(
            fixtureOwner,
            defaultFixtureRequest(identity, fixtureFormCount, fixtureFieldCount, fixtureSubmissionCount)
        );
        const primary = forms[0];

        if (primary === undefined) {
            throw new Error('e2e:fixture returned no form.');
        }

        await use({ primary, forms });
    },

    fixtureSubmissions: async (
        { fixtureForm },
        use: (submissions: { formId: string; submissionIds: string[] }) => Promise<void>
    ): Promise<void> => {
        await use({ formId: fixtureForm.primary.formId, submissionIds: fixtureForm.primary.submissionIds });
    },
});

export { expect } from '@playwright/test';
