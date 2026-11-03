import { onMounted, onUnmounted, ref, type Ref } from 'vue';
import type { TFormAnswersView } from '@/types/formAnswersView';

const STORAGE_KEY = 'd-form:form-answers:view';
const DEFAULT_VIEW: TFormAnswersView = 'table';

function isFormAnswersView(value: string | null): value is TFormAnswersView {
    return value === 'table' || value === 'card';
}

/** Owns the persisted answer presentation and keeps breakpoint state SSR-safe. */
export function useFormAnswersView(): {
    view: Ref<TFormAnswersView>;
    setView: (next: TFormAnswersView) => void;
    isDesktop: Ref<boolean>;
} {
    const view = ref<TFormAnswersView>(DEFAULT_VIEW);
    const isDesktop = ref(false);
    let desktopBreakpoint: MediaQueryList | null = null;

    const syncBreakpoint = (): void => {
        isDesktop.value = desktopBreakpoint?.matches ?? false;
    };

    onMounted(() => {
        desktopBreakpoint = window.matchMedia('(min-width: 768px)');
        syncBreakpoint();

        try {
            const storedView = window.localStorage.getItem(STORAGE_KEY);
            view.value = isFormAnswersView(storedView) ? storedView : DEFAULT_VIEW;
        } catch {
            view.value = DEFAULT_VIEW;
        }

        desktopBreakpoint.addEventListener('change', syncBreakpoint);
    });

    onUnmounted(() => {
        desktopBreakpoint?.removeEventListener('change', syncBreakpoint);
    });

    const setView = (next: TFormAnswersView): void => {
        view.value = next;
        try {
            window.localStorage.setItem(STORAGE_KEY, next);
        } catch {
            // Keep the in-memory preference when browser storage is unavailable.
        }
    };

    return { view, setView, isDesktop };
}
