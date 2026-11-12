import { onMounted, onUnmounted, ref, type Ref } from 'vue';

import type { TFormAnswerMobileView } from '@/types/formAnswerMobileView';
import { matchesMediaQuery, onMediaQueryChange } from '@/adapters/browser/mediaQuery';
import { readStorage, writeStorage } from '@/adapters/browser/storage';

const STORAGE_KEY = 'd-form:form-answers:mobile-view';
const DESKTOP_MEDIA_QUERY = '(min-width: 768px)';

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
    let releaseMediaQuery: () => void = () => undefined;

    onMounted(() => {
        view.value = readStorage({ area: 'local', key: STORAGE_KEY }) === 'list' ? 'list' : 'card';
        isDesktop.value = matchesMediaQuery(DESKTOP_MEDIA_QUERY);
        releaseMediaQuery = onMediaQueryChange(DESKTOP_MEDIA_QUERY, (matches: boolean): void => {
            isDesktop.value = matches;
        });
    });

    onUnmounted(() => releaseMediaQuery());

    const setView = (nextView: TFormAnswerMobileView): void => {
        view.value = nextView;
        // Keep the in-memory preference when browser storage is unavailable.
        writeStorage({ area: 'local', key: STORAGE_KEY, value: nextView });
    };

    return { view, isDesktop, setView };
}
