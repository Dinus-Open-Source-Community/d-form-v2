export type TTone = 'primary' | 'success' | 'warning' | 'destructive' | 'neutral' | 'info';

export type TSurface = 'base' | 'soft' | 'tinted';

export type TRadiusToken = 'sm' | 'md' | 'lg' | 'xl' | 'full';

export type TShadowToken = 'none' | 'xs' | 'sm';

export type TGapToken = 'xs' | 'sm' | 'md' | 'lg' | 'xl';

export interface IToneStyle {
    readonly border: string;
    readonly background: string;
    readonly text: string;
}
