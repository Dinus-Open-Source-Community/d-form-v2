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
    ['/admin', ORGANIZER, 'dashboard-index'],
    ['/admin/dashboard', ORGANIZER, 'dashboard-index'],

    ['/admin/events', ORGANIZER, 'events-index'],
    ['/admin/events/archived', ORGANIZER, 'events-index'],
    ['/admin/events/create', ORGANIZER, 'event-form'],
    ['/admin/events/01a1-abc/edit', ORGANIZER, 'event-form'],
    ['/admin/events/01a1-abc/forms/create', ORGANIZER, 'form-builder'],
    ['/admin/events/01a1-abc/forms/01b2-def/edit', ORGANIZER, 'form-builder'],
    ['/admin/events/01a1-abc/forms/01b2-def/submissions', ORGANIZER, 'form-builder'],
    ['/admin/events/01a1-abc/forms/01b2-def', ORGANIZER, 'form-builder'],
    ['/admin/events/01a1-abc/scan', ORGANIZER, 'scan-global'],
    ['/admin/events/01a1-abc', ORGANIZER, 'events-show'],

    ['/admin/users', ORGANIZER, 'users-index'],
    ['/admin/users/01a1-abc', ORGANIZER, 'users-show'],

    ['/admin/recruitment', ORGANIZER, 'recruitment-index'],
    ['/admin/recruitment/periods', ORGANIZER, 'recruitment-index'],
    ['/admin/recruitment/periods/create', ORGANIZER, 'recruitment-period-create'],
    ['/admin/recruitment/periods/01a1-abc/applications/01b2-def', ORGANIZER, 'detail'],
    ['/admin/recruitment/periods/01a1-abc', ORGANIZER, 'recruitment-period'],
    ['/admin/recruitment/activity-logs', ORGANIZER, 'activity-logs'],
    ['/admin/recruitment/reports', ORGANIZER, 'recruitment-index'],
    ['/admin/recruitment/interview-sessions', ORGANIZER, 'recruitment-index'],
    ['/admin/recruitment/interview-sessions/01a1-abc', ORGANIZER, 'interview-session'],
    ['/admin/recruitment/my-interviews', ORGANIZER, 'my-interviews'],
    ['/admin/recruitment/my-interviews/01a1-abc', ORGANIZER, 'my-interview'],
    ['/admin/recruitment/attendance-scan', ORGANIZER, 'scan-global'],

    ['/admin/scan', PARTICIPANT, 'scan-global'],
    ['/profile#koneksi', PARTICIPANT, 'profile'],

    ['/browse', PARTICIPANT, 'user-events'],
    ['/joined', PARTICIPANT, 'user-events'],
    ['/joined/profile', PARTICIPANT, 'profile'],
    ['/joined/team-invitations/tok-123', PARTICIPANT, 'form-fill'],
    ['/joined/01a1-abc/registration', PARTICIPANT, 'event-registration'],
    ['/joined/01a1-abc/forms/01b2-def/fill', PARTICIPANT, 'form-fill'],
    ['/joined/01a1-abc/forms/01b2-def/submitted', PARTICIPANT, 'form-success'],
    ['/joined/01a1-abc', PARTICIPANT, 'user-event-detail'],

    ['/events', PARTICIPANT, 'generic'],
    ['/', PARTICIPANT, 'generic'],
    ['', PARTICIPANT, 'generic'],
];

describe('resolveSkeletonKey', () => {
    it.each(CASES)('memetakan %s', (url: string, context: ISkeletonContext, expected: TSkeletonKey) => {
        expect(resolveSkeletonKey(url, context)).toBe(expected);
    });
});
