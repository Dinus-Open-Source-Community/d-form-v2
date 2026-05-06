export type AuthToastType = 'success' | 'error'

export interface AuthToastPayload {
    readonly type: AuthToastType
    readonly message: string
}

export interface PasswordRule {
    readonly label: string
    readonly met: boolean
}

export type PasswordStrength = 'weak' | 'fair' | 'good' | 'strong'
