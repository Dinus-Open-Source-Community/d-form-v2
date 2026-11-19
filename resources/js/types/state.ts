export type TStateIllustration = 'error' | 'empty';

export type TStatePanelVariant = 'page' | 'inline';

export type TStatePanelHeadingTag = 'h1' | 'h2' | 'p';

export type TEmptyStateKind = 'empty' | 'search' | 'filter' | 'not-found';

export type TErrorStateStatus = 403 | 404 | 409 | 429 | 500 | 502 | 503;

export interface IStateContent {
    readonly title: string;
    readonly description: string;
}

export interface IEmptyStateContentInput {
    readonly kind: TEmptyStateKind;
    readonly query?: string;
}

export interface IStatePanelProps {
    readonly illustration: TStateIllustration;
    readonly title: string;
    readonly description?: string;
    readonly variant?: TStatePanelVariant;
    readonly headingTag?: TStatePanelHeadingTag;
    readonly surface?: boolean;
}

export interface IEmptyStateProps {
    readonly kind: TEmptyStateKind;
    readonly title?: string;
    readonly description?: string;
    readonly query?: string;
    readonly surface?: boolean;
}
