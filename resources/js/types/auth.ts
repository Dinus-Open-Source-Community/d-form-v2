export interface IPasswordRule {
    readonly label: string;
    readonly met: boolean;
}

export type TPasswordStrength = 'weak' | 'fair' | 'good' | 'strong';
