import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { effectScope, type EffectScope } from 'vue';
import { useInertiaReloading, type IInertiaReloading } from '@/hooks/useInertiaReloading';

const inertiaMock = vi.hoisted(() => ({ on: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({ router: { on: inertiaMock.on } }));

type TEventHandler = (payload: unknown) => void;

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

function mountHook(currentPathname: string): IInertiaReloading {
    let api: IInertiaReloading | undefined;

    scope.run(() => {
        api = useInertiaReloading({ currentPathname: () => currentPathname });
    });

    if (api === undefined) throw new Error('useInertiaReloading tidak mengembalikan state');

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
});

describe('useInertiaReloading', () => {
    it('menandai reload saat GET preserveState pada path yang sama', () => {
        const state = mountHook('/admin/events');

        emitStart('get', '/admin/events?page=2', true);

        expect(state.reloading.value).toBe(true);
    });

    it('tidak menandai navigasi lintas halaman', () => {
        const state = mountHook('/admin/events');

        emitStart('get', '/admin/users', false);

        expect(state.reloading.value).toBe(false);
    });

    it('tidak menandai kunjungan POST', () => {
        const state = mountHook('/admin/events');

        emitStart('post', '/admin/events?page=2', true);

        expect(state.reloading.value).toBe(false);
    });

    it('membersihkan state saat finish dan cancel', () => {
        const state = mountHook('/admin/events');

        emitStart('get', '/admin/events?page=2', true);
        emit('finish');
        expect(state.reloading.value).toBe(false);

        emitStart('get', '/admin/events?page=3', true);
        emit('cancel');
        expect(state.reloading.value).toBe(false);
    });

    it('melepas listener saat unmount', () => {
        mountHook('/admin/events');

        scope.stop();

        expect(unsubscribers).toHaveLength(3);
        for (const unsubscribe of unsubscribers) {
            expect(unsubscribe).toHaveBeenCalledTimes(1);
        }
    });
});
