import { formatDate } from '@/lib/dummyData'

export const REGISTRANTS_TAB_ITEMS: {
    value: 'all' | 'pending' | 'approved' | 'rejected'
    label: string
    tone: 'default' | 'warning' | 'success' | 'destructive'
}[] = [
    { value: 'all', label: 'All', tone: 'default' },
    { value: 'pending', label: 'Pending', tone: 'warning' },
    { value: 'approved', label: 'Approved', tone: 'success' },
    { value: 'rejected', label: 'Rejected', tone: 'destructive' },
]

export const REGISTRANTS_TONE_STYLES: Record<
    'primary' | 'warning' | 'success' | 'destructive',
    { chip: string; ring: string; bar: string; dot: string }
> = {
    primary: {
        chip: 'bg-primary/10 text-primary ring-primary/15',
        ring: 'ring-primary/15',
        bar: 'bg-primary',
        dot: 'bg-primary',
    },
    warning: {
        chip: 'bg-warning/15 text-warning-foreground ring-warning/25',
        ring: 'ring-warning/20',
        bar: 'bg-warning',
        dot: 'bg-warning',
    },
    success: {
        chip: 'bg-success/10 text-success ring-success/20',
        ring: 'ring-success/15',
        bar: 'bg-success',
        dot: 'bg-success',
    },
    destructive: {
        chip: 'bg-destructive/10 text-destructive ring-destructive/20',
        ring: 'ring-destructive/15',
        bar: 'bg-destructive',
        dot: 'bg-destructive',
    },
}

export function registrantStatusBadgeClass(s: IRegistrant['status']): string {
    if (s === 'approved') return 'bg-success/10 text-success ring-success/15'
    if (s === 'rejected') return 'bg-destructive/10 text-destructive ring-destructive/15'
    return 'bg-warning/15 text-warning-foreground ring-warning/20'
}

export function registrantInitials(name: string): string {
    return name
        .split(' ')
        .map((w) => w[0])
        .join('')
        .toUpperCase()
        .slice(0, 2)
}

export function registrantRelativeTime(dateStr: string): string {
    const diff = Date.now() - new Date(dateStr).getTime()
    const minutes = Math.floor(diff / 60000)
    if (minutes < 1) return 'just now'
    if (minutes < 60) return `${minutes}m ago`
    const hours = Math.floor(minutes / 60)
    if (hours < 24) return `${hours}h ago`
    const days = Math.floor(hours / 24)
    if (days < 7) return `${days}d ago`
    return formatDate(dateStr)
}
