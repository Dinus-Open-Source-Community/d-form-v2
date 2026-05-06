import type { LottieRegistry } from '@/types/lottie'

/**
 * Lottie registry — single source of truth for animated illustrations.
 *
 * To add a new Lottie:
 *   1. Download the JSON from https://lottiefiles.com (prefer free / CC0 / CC-BY).
 *   2. Save it to `public/lotties/<kebab-name>.json`.
 *   3. Add a new entry below using a camelCase key.
 *
 * `LocalLottie` resolves entries by `name` prop.
 */
export const lotties = {
    heroProduct: {
        src: '/lotties/hero-product.json',
        label: 'Product workspace illustration',
    },
    stepsFlow: {
        src: '/lotties/steps-flow.json',
        label: 'Three-step flow illustration',
    },
    featureModules: {
        src: '/lotties/feature-modules.json',
        label: 'Feature module stack illustration',
    },
    docsFlow: {
        src: '/lotties/docs-flow.json',
        label: 'Documentation flow illustration',
    },
    authSecure: {
        src: '/lotties/auth-secure.json',
        label: 'Secure authentication illustration',
    },
    builderEmpty: {
        src: '/lotties/builder-empty.json',
        label: 'Builder empty state illustration',
    },
    fieldSelected: {
        src: '/lotties/field-selected.json',
        label: 'Field inspector illustration',
    },
    comingSoon: {
        src: '/lotties/coming-soon.json',
        label: 'Coming soon illustration',
    },
    errorState: {
        src: '/lotties/error-state.json',
        label: 'Error state illustration',
    },
    emptyData: {
        src: '/lotties/empty-data.json',
        label: 'Empty data illustration',
    },
    successCheck: {
        src: '/lotties/success-check.json',
        label: 'Success check illustration',
    },
    loadingDots: {
        src: '/lotties/loading-dots.json',
        label: 'Loading indicator',
    },
    eventsLive: {
        src: '/lotties/events-live.json',
        label: 'Live events illustration',
    },
    analyticsPulse: {
        src: '/lotties/analytics-pulse.json',
        label: 'Analytics pulse illustration',
    },
} as const satisfies LottieRegistry

export type LottieName = keyof typeof lotties
