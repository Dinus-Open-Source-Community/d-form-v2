import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { expect, test } from '@playwright/test';
import { assertAdminState } from './support/authGuard';

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
