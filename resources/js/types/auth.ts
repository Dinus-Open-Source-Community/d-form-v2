export type TAuthToastType = 'success' | 'error';

export interface IAuthToastPayload {
    readonly type: TAuthToastType;
    readonly message: string;
}

export interface IPasswordRule {
    readonly label: string;
    readonly met: boolean;
}

export type TPasswordStrength = 'weak' | 'fair' | 'good' | 'strong';
