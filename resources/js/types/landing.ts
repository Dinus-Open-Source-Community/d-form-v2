import type { IconComponent } from '@/types/icons'
import type { LottieName } from '@/lib/lotties'

export interface FeatureItem {
    readonly icon: IconComponent
    readonly title: string
    readonly description: string
}

export interface StepItem {
    readonly title: string
    readonly description: string
    readonly lottie?: LottieName
}

export interface NavLink {
    readonly label: string
    readonly href: string
}

export interface IntegrationLogo {
    readonly name: string
    readonly category: string
}

export interface ComparisonRow {
    readonly feature: string
    readonly dform: boolean | string
    readonly competitor: boolean | string
}

export interface EventListItem {
    readonly id: string | number
    readonly title: string
    readonly date: string
    readonly location: string
    readonly attendees: number
    readonly category: string
    readonly imageUrl?: string
    readonly slug?: string
}
