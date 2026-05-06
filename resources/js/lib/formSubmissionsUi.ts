export function formatSubmissionDate(value: string): string {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

export function submissionUserInitials(name: string): string {
    return name
        .split(' ')
        .map((w) => w[0])
        .join('')
        .toUpperCase()
        .slice(0, 2)
}

export function humanizeSubmissionKey(fieldLabelMap: Record<string, string>, value: string): string {
    return fieldLabelMap[value] || value.replace(/^field_/, '').replace(/_/g, ' ')
}

export function answerPreview(value: unknown): string {
    if (Array.isArray(value)) return value.map(String).join(', ')
    if (typeof value === 'string') return value
    if (value === null || value === undefined) return 'Empty'
    if (typeof value === 'number' || typeof value === 'boolean') return String(value)
    return 'Structured answer'
}

export function submissionFileUrl(value: unknown): string | null {
    return typeof value === 'string' && value.includes('/') ? `/storage/${value}` : null
}

export function submissionPaginationLabel(value: string): string {
    return value.replace('&laquo;', 'Previous').replace('&raquo;', 'Next')
}
