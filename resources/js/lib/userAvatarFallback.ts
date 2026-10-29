/** Background + icon color classes for avatars without a photo (fixed palette; index chosen from seed). */
export const USER_AVATAR_FALLBACK_PALETTE: readonly [
    { bg: string; icon: string },
    ...{ bg: string; icon: string }[],
] = [
    { bg: 'bg-blue-100 dark:bg-blue-950/60', icon: 'text-blue-600 dark:text-blue-400' },
    { bg: 'bg-emerald-100 dark:bg-emerald-950/60', icon: 'text-emerald-600 dark:text-emerald-400' },
    { bg: 'bg-amber-100 dark:bg-amber-950/60', icon: 'text-amber-700 dark:text-amber-400' },
    { bg: 'bg-violet-100 dark:bg-violet-950/60', icon: 'text-violet-600 dark:text-violet-400' },
]

function hashSeed(seed: string): number {
    let h = 0
    for (let i = 0; i < seed.length; i++) h = (Math.imul(31, h) + seed.charCodeAt(i)) | 0
    return Math.abs(h)
}

export function userAvatarFallbackPaletteIndex(seed: string): number {
    if (!seed) return 0
    return hashSeed(seed) % USER_AVATAR_FALLBACK_PALETTE.length
}

export function userAvatarFallbackClasses(seed: string): { bg: string; icon: string } {
    return USER_AVATAR_FALLBACK_PALETTE[userAvatarFallbackPaletteIndex(seed)]
}

/** Stable seed: prefer user id, then email (lowercased). */
export function userAvatarSeed(user: { id?: string | null; email?: string | null } | null | undefined): string {
    if (!user) return 'anonymous'
    const id = user.id?.trim()
    if (id) return id
    const email = user.email?.trim()
    if (email) return email.toLowerCase()
    return 'anonymous'
}
