import { onMounted, onUnmounted, readonly, ref, type Ref } from 'vue';
import { findElementById } from '@/adapters/browser/domQuery';
import { readWindow } from '@/adapters/browser/environment';

export interface IUseViewportRevealOptions {
    targetId: string;
    threshold: number;
}

export interface IUseViewportRevealResult {
    visible: Readonly<Ref<boolean>>;
}

/** Reveals a section once it enters the viewport and releases its observer on unmount. */
export function useViewportReveal(options: IUseViewportRevealOptions): IUseViewportRevealResult {
    const visible = ref(false);
    let observer: IntersectionObserver | null = null;

    onMounted(() => {
        const windowRef = readWindow();
        const prefersReducedMotion = windowRef?.matchMedia('(prefers-reduced-motion: reduce)').matches ?? false;
        if (prefersReducedMotion) {
            visible.value = true;
            return;
        }

        const element = findElementById(options.targetId);
        if (element === null) return;

        observer = new IntersectionObserver(
            ([entry]) => {
                if (entry?.isIntersecting) {
                    visible.value = true;
                    observer?.disconnect();
                    observer = null;
                }
            },
            { threshold: options.threshold }
        );
        observer.observe(element);
    });

    onUnmounted(() => {
        observer?.disconnect();
        observer = null;
    });

    return { visible: readonly(visible) };
}
