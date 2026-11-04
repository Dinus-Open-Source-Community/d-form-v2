import { onMounted, onUnmounted, ref, type Ref } from 'vue';

import type { TFormAnswerMobileView } from '@/types/formAnswerMobileView';

const STORAGE_KEY = 'd-form:form-answers:mobile-view';

/** Loads the saved mobile answer presentation, falling back to cards when storage is unavailable. */
export function useFormAnswerMobileView(): {
    view: Ref<TFormAnswerMobileView>;
    isDesktop: Ref<boolean>;
    setView: (view: TFormAnswerMobileView) => void;
} {
    // Keep SSR and the first client render identical; detect the breakpoint after hydration.
    const initialIsDesktop = false;
    const view = ref<TFormAnswerMobileView>('card');
    const isDesktop = ref(initialIsDesktop);
    let breakpoint: MediaQueryList | null = null;
    const syncBreakpoint = (event: MediaQueryList | MediaQueryListEvent): void => {
        isDesktop.value = event.matches;
    };

    onMounted(() => {
        try {
            view.value = window.localStorage.getItem(STORAGE_KEY) === 'list' ? 'list' : 'card';
        } catch {
            view.value = 'card';
        }
        breakpoint = window.matchMedia('(min-width: 768px)');
        syncBreakpoint(breakpoint);
        breakpoint.addEventListener('change', syncBreakpoint);
    });
    onUnmounted(() => breakpoint?.removeEventListener('change', syncBreakpoint));
    const setView = (nextView: TFormAnswerMobileView): void => {
        view.value = nextView;
        try {
            window.localStorage.setItem(STORAGE_KEY, nextView);
        } catch {
            // Keep the in-memory preference when browser storage is unavailable.
        }
    };

    return { view, isDesktop, setView };
}
