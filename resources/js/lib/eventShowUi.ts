export function parseEventCategories(raw: unknown): string[] {
    if (Array.isArray(raw)) return raw.map((s) => String(s).trim()).filter(Boolean)
    if (typeof raw === 'string') return raw.split(',').map((s) => s.trim()).filter(Boolean)
    return []
}

export function eventParticipantInitials(name: string): string {
    return name.split(' ').map((w) => w[0]).join('').toUpperCase().slice(0, 2)
}
