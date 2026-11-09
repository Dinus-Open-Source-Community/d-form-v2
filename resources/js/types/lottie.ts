export interface ILottieRegistryEntry {
    readonly src: string;
    readonly label: string;
}

/**
 * Keys of the runtime registry in `lib/lotties.ts`. Declared here so `types/**`
 * never imports `lib/**`; the registry is compiled against this list.
 */
export type TLottieName =
    | 'builderEmpty'
    | 'fieldSelected'
    | 'errorState'
    | 'error404Page'
    | 'emptyData'
    | 'registrantsWaiting'
    | 'landingHero'
    | 'landingStepCreate'
    | 'landingStepBuild'
    | 'landingStepMonitor'
    | 'landingShowcase'
    | 'landingCta'
    | 'featuresHero'
    | 'featuresIntegrations'
    | 'eventsHero';

/** Registry shape derived from the declared name list; entry type is reused, not duplicated. */
export type TLottieRegistry = Readonly<Record<TLottieName, ILottieRegistryEntry>>;

export interface ILocalLottieProps {
    readonly name?: string;
    readonly src?: string;
    readonly animationLink?: string;
    readonly height?: number | string;
    readonly width?: number | string;
    readonly loop?: boolean | number;
    readonly autoPlay?: boolean;
    readonly speed?: number;
    readonly lazy?: boolean;
}
