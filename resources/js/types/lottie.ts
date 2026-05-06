export interface LottieRegistryEntry {
    readonly src: string
    readonly label: string
}

export type LottieRegistry = Readonly<Record<string, LottieRegistryEntry>>

export interface LocalLottieProps {
    readonly name?: string
    readonly src?: string
    readonly animationLink?: string
    readonly height?: number | string
    readonly width?: number | string
    readonly loop?: boolean | number
    readonly autoPlay?: boolean
    readonly speed?: number
    readonly lazy?: boolean
}
