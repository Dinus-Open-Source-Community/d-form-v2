import type { TIconComponent } from '@/types/icons';
import type { TLottieName } from '@/types/lottie';

export interface IFeatureItem {
    readonly icon: TIconComponent;
    readonly title: string;
    readonly description: string;
}

export interface IStepItem {
    readonly title: string;
    readonly description: string;
    readonly lottie?: TLottieName;
}

export interface INavLink {
    readonly label: string;
    readonly href: string;
}

export interface IIntegrationLogo {
    readonly name: string;
    readonly category: string;
}

export interface IComparisonRow {
    readonly feature: string;
    readonly dform: boolean | string;
    readonly competitor: boolean | string;
}

export interface IEventListItem {
    readonly id: string | number;
    readonly title: string;
    readonly date: string;
    readonly location: string;
    readonly attendees: number;
    readonly category: string;
    readonly imageUrl?: string;
    readonly slug?: string;
}
