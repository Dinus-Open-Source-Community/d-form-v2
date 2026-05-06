export type Tone = 'primary' | 'success' | 'warning' | 'destructive' | 'neutral' | 'info'

export type Surface = 'base' | 'soft' | 'tinted'

export type RadiusToken = 'sm' | 'md' | 'lg' | 'xl' | 'full'

export type ShadowToken = 'none' | 'xs' | 'sm'

export type GapToken = 'xs' | 'sm' | 'md' | 'lg' | 'xl'

export interface ToneStyle {
    readonly border: string
    readonly background: string
    readonly text: string
}
