// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { createSSRApp, defineComponent, h } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { clearTopbar, provideTopbar, setTopbar, useTopbar } from '../useDashboardTopbar';

describe('useDashboardTopbar FLOW-P2-006', () => {
    it('isolates state across independent provider instances and supports clear', async () => {
        const snapshots: Array<{ title: string | null; subtitle: string | null }> = [];
        const makeApp = (): ReturnType<typeof createSSRApp> =>
            createSSRApp(
                defineComponent({
                    setup() {
                        const provider = provideTopbar();
                        provider.setTopbar({ title: 'First', subtitle: 'Detail' });
                        snapshots.push({ title: provider.title.value, subtitle: provider.subtitle.value });
                        provider.clearTopbar();
                        snapshots.push({ title: provider.title.value, subtitle: provider.subtitle.value });
                        return () =>
                            h(
                                defineComponent({
                                    setup() {
                                        const topbar = useTopbar();
                                        return () => h('div', topbar.title.value ?? '');
                                    },
                                })
                            );
                    },
                })
            );

        await renderToString(makeApp());
        await renderToString(makeApp());
        expect(snapshots).toEqual([
            { title: 'First', subtitle: 'Detail' },
            { title: null, subtitle: null },
            { title: 'First', subtitle: 'Detail' },
            { title: null, subtitle: null },
        ]);
    });

    it('shares provider state with an injected child', async () => {
        const observedTitles: Array<string | null> = [];
        const app = createSSRApp(
            defineComponent({
                setup() {
                    provideTopbar();
                    return () =>
                        h(
                            defineComponent({
                                setup() {
                                    const topbar = useTopbar();
                                    setTopbar({ title: 'Provider title' });
                                    observedTitles.push(topbar.title.value);
                                    clearTopbar();
                                    observedTitles.push(topbar.title.value);
                                    return () => h('span', topbar.title.value ?? '');
                                },
                            })
                        );
                },
            })
        );
        await renderToString(app);
        expect(observedTitles).toEqual(['Provider title', null]);
    });

    it('throws a clear error when page-facing actions have no provider', async () => {
        const errors: string[] = [];
        const app = createSSRApp(
            defineComponent({
                setup() {
                    for (const action of [(): void => setTopbar({ title: 'Orphaned' }), (): void => clearTopbar()]) {
                        try {
                            action();
                        } catch (error: unknown) {
                            if (error instanceof Error) {
                                errors.push(error.message);
                            }
                        }
                    }
                    return () => h('div');
                },
            })
        );
        await renderToString(app);
        expect(errors).toEqual([
            'useTopbar must be used within a dashboard topbar provider',
            'useTopbar must be used within a dashboard topbar provider',
        ]);
    });
});
