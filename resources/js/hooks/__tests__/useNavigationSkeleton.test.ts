import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { effectScope, type EffectScope } from 'vue';
import {
    NAVIGATION_SKELETON_DELAY_MS,
    useNavigationSkeleton,
    type INavigationSkeleton,
    type INavigationSkeletonOptions,
} from '@/hooks/useNavigationSkeleton';

const inertiaMock = vi.hoisted(() => ({ on: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({ router: { on: inertiaMock.on } }));

type TEventHandler = (payload: unknown) => void;

/** Payload minimal `start` yang dibaca hook; sisanya milik Inertia. */
interface IStartPayload {
    readonly detail: {
        readonly visit: {
            readonly method: string;
            readonly url: string;
            readonly preserveState?: boolean;
        };
    };
}

let handlers: Map<string, TEventHandler>;
let unsubscribers: Array<ReturnType<typeof vi.fn>>;
let scope: EffectScope;

function mountHook(options?: INavigationSkeletonOptions): INavigationSkeleton {
    let api: INavigationSkeleton | undefined;

    scope.run(() => {
        api = useNavigationSkeleton(options);
    });

    if (api === undefined) throw new Error('useNavigationSkeleton tidak mengembalikan state');

    return api;
}

function emitStart(method: string, url: string, preserveState?: boolean): void {
    const handler = handlers.get('start');
    if (handler === undefined) throw new Error('handler start belum terpasang');

    const payload: IStartPayload = { detail: { visit: { method, url, preserveState } } };
    handler(payload);
}

function emit(event: string): void {
    const handler = handlers.get(event);
    if (handler === undefined) throw new Error(`handler ${event} belum terpasang`);
    handler({});
}

beforeEach(() => {
    vi.useFakeTimers();
    handlers = new Map();
    unsubscribers = [];
    scope = effectScope();

    inertiaMock.on.mockReset();
    inertiaMock.on.mockImplementation((event: string, handler: TEventHandler) => {
        handlers.set(event, handler);
        const unsubscribe = vi.fn();
        unsubscribers.push(unsubscribe);

        return unsubscribe;
    });
});

afterEach(() => {
    scope.stop();
    vi.useRealTimers();
});

describe('useNavigationSkeleton', () => {
    it('memasang skeleton setelah ambang tunda untuk kunjungan GET', () => {
        const skeleton = mountHook({ currentPathname: () => '/admin/users' });

        emitStart('get', '/admin/events', false);
        expect(skeleton.pending.value).toBe(false);

        vi.advanceTimersByTime(NAVIGATION_SKELETON_DELAY_MS);

        expect(skeleton.pending.value).toBe(true);
        expect(skeleton.skeletonKey.value).toBe('events-index');
    });

    it('belum memasang skeleton sebelum ambang tunda', () => {
        const skeleton = mountHook({ currentPathname: () => '/admin/users' });

        emitStart('get', '/admin/events', false);
        vi.advanceTimersByTime(NAVIGATION_SKELETON_DELAY_MS - 1);

        expect(skeleton.pending.value).toBe(false);
    });

    it('tidak memasang skeleton untuk kunjungan POST', () => {
        const skeleton = mountHook({ currentPathname: () => '/admin/events' });

        emitStart('post', '/admin/events', false);
        vi.advanceTimersByTime(NAVIGATION_SKELETON_DELAY_MS);

        expect(skeleton.pending.value).toBe(false);
        expect(skeleton.skeletonKey.value).toBeNull();
    });

    it('tidak memasang skeleton untuk partial reload preserveState pada path yang sama', () => {
        const skeleton = mountHook({ currentPathname: () => '/admin/events' });

        emitStart('get', '/admin/events?page=2', true);
        vi.advanceTimersByTime(NAVIGATION_SKELETON_DELAY_MS);

        expect(skeleton.pending.value).toBe(false);
    });

    it('tetap memasang skeleton untuk preserveState ke path yang berbeda', () => {
        const skeleton = mountHook({ currentPathname: () => '/admin/events' });

        emitStart('get', '/admin/users', true);
        vi.advanceTimersByTime(NAVIGATION_SKELETON_DELAY_MS);

        expect(skeleton.pending.value).toBe(true);
        expect(skeleton.skeletonKey.value).toBe('users-index');
    });

    it('jatuh ke skeleton generic saat peserta membuka /dashboard', () => {
        const skeleton = mountHook({
            currentPathname: () => '/profile',
            context: () => ({ canManageEvents: false }),
        });

        emitStart('get', '/dashboard', false);
        vi.advanceTimersByTime(NAVIGATION_SKELETON_DELAY_MS);

        expect(skeleton.skeletonKey.value).toBe('generic');
    });

    it('membersihkan skeleton saat finish', () => {
        const skeleton = mountHook({ currentPathname: () => '/admin/users' });

        emitStart('get', '/admin/events', false);
        vi.advanceTimersByTime(NAVIGATION_SKELETON_DELAY_MS);
        expect(skeleton.pending.value).toBe(true);

        emit('finish');

        expect(skeleton.pending.value).toBe(false);
        expect(skeleton.skeletonKey.value).toBeNull();
    });

    it('membersihkan skeleton saat visit dibatalkan', () => {
        const skeleton = mountHook({ currentPathname: () => '/admin/users' });

        emitStart('get', '/admin/events', false);
        vi.advanceTimersByTime(NAVIGATION_SKELETON_DELAY_MS);

        emit('cancel');

        expect(skeleton.pending.value).toBe(false);
        expect(skeleton.skeletonKey.value).toBeNull();
    });

    it('melepas seluruh listener saat unmount', () => {
        mountHook({ currentPathname: () => '/admin/users' });

        scope.stop();

        expect(unsubscribers).toHaveLength(3);
        for (const unsubscribe of unsubscribers) {
            expect(unsubscribe).toHaveBeenCalledTimes(1);
        }
    });
});
