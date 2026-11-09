import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { expect, test } from '@playwright/test';
import { adminStatePath, assertAdminState } from './support/authGuard';

/**
 * H-auth-guard: kontrak guard storageState tanpa browser dan tanpa navigasi.
 * Guard wajib melempar error berisi path yang hilang dan hint `test:e2e:setup`.
 */
test('H-auth-guard: state admin hilang melempar error yang menyebut path dan hint setup', (): void => {
    const missingStatePath = join(tmpdir(), `e2e-missing-admin-state-${process.pid}-${Date.now()}.json`);
    const runGuard = (): void => {
        assertAdminState(missingStatePath);
    };

    expect(runGuard).toThrow(missingStatePath);
    expect(runGuard).toThrow('test:e2e:setup');
});

test('H-auth-guard: path default storageState diturunkan dari E2E_RUN_ID', (): void => {
    const env = { E2E_RUN_ID: '20260102T030405-abcdef' } as NodeJS.ProcessEnv;

    expect(adminStatePath({ env })).toBe('tests/e2e/.auth/admin-20260102T030405-abcdef.json');
});

test('H-auth-guard: E2E_ADMIN_STATE tetap menang atas path per run', (): void => {
    const env = {
        E2E_RUN_ID: '20260102T030405-abcdef',
        E2E_ADMIN_STATE: 'tests/e2e/.auth/admin-8020.json',
    } as NodeJS.ProcessEnv;

    expect(adminStatePath({ env })).toBe('tests/e2e/.auth/admin-8020.json');
    expect(adminStatePath({ env, runId: '20260102T030405-abcdef' })).toBe('tests/e2e/.auth/admin-8020.json');
});
