import { onMounted, onUnmounted, ref, type Ref } from 'vue';

export type FormAnswerMobileView = 'card' | 'list';

const STORAGE_KEY = 'd-form:form-answers:mobile-view';

/** Loads the saved mobile answer presentation, falling back to cards when storage is unavailable. */
export function useFormAnswerMobileView(): {
    view: Ref<FormAnswerMobileView>;
    isDesktop: Ref<boolean>;
    setView: (view: FormAnswerMobileView) => void;
} {
    let initialView: FormAnswerMobileView = 'card';
    let initialIsDesktop = false;
    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);
        initialView = stored === 'list' ? 'list' : 'card';
    } catch {
        initialView = 'card';
    }
    try {
        initialIsDesktop = window.matchMedia('(min-width: 768px)').matches;
    } catch {
        initialIsDesktop = false;
    }

    const view = ref<FormAnswerMobileView>(initialView);
    const isDesktop = ref(initialIsDesktop);
    let breakpoint: MediaQueryList | null = null;
    const syncBreakpoint = (event: MediaQueryList | MediaQueryListEvent): void => {
        isDesktop.value = event.matches;
    };

    onMounted(() => {
        breakpoint = window.matchMedia('(min-width: 768px)');
        syncBreakpoint(breakpoint);
        breakpoint.addEventListener('change', syncBreakpoint);
    });
    onUnmounted(() => breakpoint?.removeEventListener('change', syncBreakpoint));
    const setView = (nextView: FormAnswerMobileView): void => {
        view.value = nextView;
        try {
            window.localStorage.setItem(STORAGE_KEY, nextView);
        } catch {
            // Keep the in-memory preference when browser storage is unavailable.
        }
    };

    return { view, isDesktop, setView };
}
