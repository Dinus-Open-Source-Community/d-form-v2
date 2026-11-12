import type { Directive } from 'vue';

/** Focuses an element when the directive value is truthy; SSR stays DOM-free. */
export const focusDirective: Directive<HTMLElement, boolean> = {
    mounted: (element, binding): void => {
        if (binding.value) element.focus();
    },
    getSSRProps: (): Record<string, never> => ({}),
};
