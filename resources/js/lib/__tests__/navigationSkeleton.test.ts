import { describe, expect, it } from 'vitest';
import { resolveSkeletonKey } from '@/lib/navigationSkeleton';
import type { ISkeletonContext, TSkeletonKey } from '@/types/skeleton';

const ORGANIZER: ISkeletonContext = { canManageEvents: true };
const PARTICIPANT: ISkeletonContext = { canManageEvents: false };

/** Satu baris tabel: url masuk, konteks pengguna, dan kunci skeleton yang diharapkan. */
type TSkeletonCase = [url: string, context: ISkeletonContext, expected: TSkeletonKey];

const CASES: readonly TSkeletonCase[] = [
    ['/dashboard', ORGANIZER, 'dashboard-index'],
    ['/dashboard?tab=1', ORGANIZER, 'dashboard-index'],
    ['http://localhost:3000/dashboard', PARTICIPANT, 'generic'],
    ['/admin/events', ORGANIZER, 'events-index'],
    ['/admin/events/archived', ORGANIZER, 'list'],
    ['/admin/events/create', ORGANIZER, 'form'],
    ['/admin/events/01a1-abc/edit', ORGANIZER, 'form'],
    ['/admin/events/01a1-abc/forms/create', ORGANIZER, 'form'],
    ['/admin/events/01a1-abc/forms/01b2-def/edit', ORGANIZER, 'form'],
    ['/admin/events/01a1-abc/forms/01b2-def/submissions', ORGANIZER, 'list'],
    ['/admin/events/01a1-abc/forms/01b2-def/fill', ORGANIZER, 'form'],
    ['/admin/events/01a1-abc/forms/01b2-def', ORGANIZER, 'form-builder'],
    ['/admin/events/01a1-abc/registration-status', ORGANIZER, 'detail'],
    ['/admin/events/01a1-abc', ORGANIZER, 'events-show'],
    ['/admin/users', ORGANIZER, 'users-index'],
    ['/admin/users/01a1-abc', ORGANIZER, 'detail'],
    ['/admin/recruitment', ORGANIZER, 'recruitment-index'],
    ['/admin/recruitment/periods', ORGANIZER, 'list'],
    ['/admin/recruitment/periods/create', ORGANIZER, 'form'],
    ['/admin/recruitment/periods/01a1-abc/applications/01b2-def', ORGANIZER, 'detail'],
    ['/admin/recruitment/periods/01a1-abc', ORGANIZER, 'recruitment-period'],
    ['/admin/recruitment/activity-logs', ORGANIZER, 'list'],
    ['/admin/recruitment/reports', ORGANIZER, 'detail'],
    ['/admin/recruitment/interview-sessions', ORGANIZER, 'list'],
    ['/admin/recruitment/interview-sessions/01a1-abc', ORGANIZER, 'detail'],
    ['/admin/recruitment/my-interviews', ORGANIZER, 'list'],
    ['/admin/recruitment/my-interviews/01a1-abc', ORGANIZER, 'detail'],
    ['/admin/scan', PARTICIPANT, 'detail'],
    ['/profile#koneksi', PARTICIPANT, 'detail'],
    ['/browse', PARTICIPANT, 'user-events'],
    ['/joined', PARTICIPANT, 'user-events'],
    ['/joined/team-invitations/tok-123', PARTICIPANT, 'form'],
    ['/joined/01a1-abc/register', PARTICIPANT, 'list'],
    ['/joined/01a1-abc/registration', PARTICIPANT, 'detail'],
    ['/joined/01a1-abc', PARTICIPANT, 'user-event-detail'],
    ['/admin/recruitment/attendance-scan', ORGANIZER, 'generic'],
    ['/events', PARTICIPANT, 'generic'],
    ['/', PARTICIPANT, 'generic'],
    ['', PARTICIPANT, 'generic'],
];

describe('resolveSkeletonKey', () => {
    it.each(CASES)('memetakan %s', (url: string, context: ISkeletonContext, expected: TSkeletonKey) => {
        expect(resolveSkeletonKey(url, context)).toBe(expected);
    });
});
