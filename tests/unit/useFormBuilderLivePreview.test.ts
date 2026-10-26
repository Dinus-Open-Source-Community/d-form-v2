import assert from 'node:assert/strict';
import { afterEach, beforeEach, describe, it } from 'node:test';
import { createRenderer, defineComponent, h } from 'vue';
import { useFormBuilderLivePreview, type IFormBuilderLivePreview } from '@/hooks/useFormBuilderLivePreview';

/** Elemen host minimal untuk renderer kustom Vue — cukup untuk `ref` template + `getBoundingClientRect`. */
class FakeElement {
    parent: FakeElement | null = null;
    props: Record<string, unknown> = {};
    text = '';
    width = 0;

    getBoundingClientRect(): DOMRect {
        return {
            width: this.width,
            height: 0,
            x: 0,
            y: 0,
            top: 0,
            left: 0,
            right: this.width,
            bottom: 0,
            toJSON: () => ({}),
        };
    }
}

/** Pengganti `ResizeObserver` global; menyimpan instance terakhir agar test bisa memicu callback. */
class StubResizeObserver implements ResizeObserver {
    static latest: StubResizeObserver | null = null;

    disconnected = false;
    observed: Element[] = [];
    readonly callback: ResizeObserverCallback;

    constructor(callback: ResizeObserverCallback) {
        this.callback = callback;
        StubResizeObserver.latest = this;
    }

    observe(target: Element): void {
        this.observed.push(target);
    }

    unobserve(): void {
        this.observed = [];
    }

    disconnect(): void {
        this.disconnected = true;
        this.observed = [];
    }
}

const renderer = createRenderer<FakeElement, FakeElement>({
    patchProp: (el, key, _previous, next) => {
        el.props[key] = next;
    },
    insert: (child, parent) => {
        child.parent = parent;
    },
    remove: (child) => {
        child.parent = null;
    },
    createElement: () => new FakeElement(),
    createText: () => new FakeElement(),
    createComment: () => new FakeElement(),
    setText: (node, text) => {
        node.text = text;
    },
    setElementText: (node, text) => {
        node.text = text;
    },
    parentNode: (node) => node.parent,
    nextSibling: () => null,
});

const originalResizeObserver = globalThis.ResizeObserver;
let api: IFormBuilderLivePreview | null = null;
let hostWidth = 0;
let app: ReturnType<typeof renderer.createApp> | null = null;

/** Mount komponen kecil yang memakai composable, dengan lebar elemen host yang bisa diatur. */
function mountComposable(): void {
    const host = new FakeElement();
    host.width = hostWidth;

    app = renderer.createApp(
        defineComponent({
            setup() {
                api = useFormBuilderLivePreview();

                return () => h('div', { ref: api?.rootRef });
            },
        }),
    );

    app.mount(host);
}

/** Picu callback observer seperti browser saat lebar elemen berubah. */
function resizeTo(width: number): void {
    const stub = StubResizeObserver.latest;
    assert.ok(stub, 'ResizeObserver belum dibuat oleh composable');

    // Test double: entry hanya perlu `contentRect.width` yang dibaca composable.
    const entry = { contentRect: { width } } as ResizeObserverEntry;
    stub.callback([entry], stub);
}

beforeEach(() => {
    globalThis.ResizeObserver = StubResizeObserver;
    StubResizeObserver.latest = null;
    api = null;
    hostWidth = 0;
});

afterEach(() => {
    app?.unmount();
    app = null;
    globalThis.ResizeObserver = originalResizeObserver;
});

describe('useFormBuilderLivePreview', () => {
    it('mengaktifkan kolom hanya setelah lebar terukur melewati ambang', () => {
        mountComposable();
        assert.equal(api?.livePreviewActive.value, false);

        resizeTo(1200);
        assert.equal(api?.livePreviewActive.value, true);
    });

    it('mematikan kembali kolom saat lebar turun di bawah ambang', () => {
        mountComposable();

        resizeTo(1200);
        assert.equal(api?.livePreviewActive.value, true);

        resizeTo(900);
        assert.equal(api?.livePreviewActive.value, false);
    });

    it('memutus observer saat komponen dilepas', () => {
        mountComposable();
        const stub = StubResizeObserver.latest;
        assert.ok(stub);

        app?.unmount();
        app = null;

        assert.equal(stub.disconnected, true);
    });

    it('tidak melempar bila ResizeObserver tidak tersedia', () => {
        // Test double: environment tanpa ResizeObserver (SSR/Node lama) harus aman.
        globalThis.ResizeObserver = undefined as unknown as typeof ResizeObserver;

        assert.doesNotThrow(() => mountComposable());
        assert.equal(api?.livePreviewActive.value, false);
    });
});
