import { describe, expect, it } from 'vitest';
import { isSkeletonKeyRegistered, loadSkeletonScreen } from '@/components/modules/dashboard/skeletons/skeletonRegistry';
import type { TSkeletonKey } from '@/types/skeleton';

const REGISTERED_KEYS: readonly TSkeletonKey[] = [
    'dashboard-index',
    'events-index',
    'events-show',
    'users-index',
    'recruitment-index',
    'recruitment-period',
    'form-builder',
    'user-events',
    'user-event-detail',
    'list',
    'detail',
    'form',
    'generic',
];

describe('skeletonRegistry', () => {
    it('mendaftarkan seluruh kunci skeleton pada peta loader', () => {
        for (const key of REGISTERED_KEYS) {
            expect(isSkeletonKeyRegistered(key), `${key} belum terdaftar`).toBe(true);
        }
    });

    it('menolak kunci yang tidak dikenal tanpa melempar error', async () => {
        expect(isSkeletonKeyRegistered('tidak-ada')).toBe(false);
        await expect(loadSkeletonScreen('tidak-ada')).resolves.toBeNull();
    });
});
