import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import { useEventListSection } from '@/hooks/useEventListSection';

const lifecycle = vi.hoisted(() => ({
    mounted: [] as Array<() => void>,
    beforeUnmount: [] as Array<() => void>,
}));

vi.mock('vue', async (importOriginal) => {
    const actual = await importOriginal<typeof import('vue')>();

    return {
        ...actual,
        onMounted: (callback: () => void): void => {
            lifecycle.mounted.push(callback);
        },
        onBeforeUnmount: (callback: () => void): void => {
            lifecycle.beforeUnmount.push(callback);
        },
    };
});

vi.mock('@inertiajs/vue3', () => ({
    router: {
        get: vi.fn(),
        on: vi.fn((): (() => void) => (): void => undefined),
    },
}));

beforeEach(() => {
    lifecycle.mounted.length = 0;
    lifecycle.beforeUnmount.length = 0;
});

describe('useEventListSection', () => {
    it('keeps the active query and cursor URLs available to the section', () => {
        const state = useEventListSection({
            filters: { q: '  workshop  ' },
            pagination: {
                links: [
                    { url: '/events?cursor=previous', label: 'Sebelumnya', active: false },
                    { url: '/events?cursor=next', label: 'Berikutnya', active: false },
                ],
                hasMore: true,
            },
        });

        expect(state.activeQuery.value).toBe('workshop');
        expect(state.cursorUrls.value).toEqual({
            previous: '/events?cursor=previous',
            next: '/events?cursor=next',
        });
        expect(state.query.value).toBe('  workshop  ');
    });

    it('starts hidden until the list section enters the viewport', () => {
        const state = useEventListSection();

        expect(state.visible.value).toBe(false);
        expect(lifecycle.mounted).toHaveLength(2);
    });

    it('tracks reactive filters and cursor links after an Inertia visit', () => {
        const filters = ref({ q: 'first' });
        const pagination = ref({
            links: [{ url: '/events?cursor=first', label: 'Berikutnya', active: false }],
            hasMore: true,
        });
        const state = useEventListSection({ filters, pagination });

        filters.value = { q: 'second' };
        pagination.value = {
            links: [{ url: '/events?cursor=second', label: 'Berikutnya', active: false }],
            hasMore: true,
        };

        expect(state.activeQuery.value).toBe('second');
        expect(state.cursorUrls.value.next).toBe('/events?cursor=second');
        expect(state.query.value).toBe('first');
    });
});
