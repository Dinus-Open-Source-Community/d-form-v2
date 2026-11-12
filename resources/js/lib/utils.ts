import type { ClassValue } from 'clsx';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

/** Gabung kelas Tailwind dengan `clsx` lalu rapikan konflik lewat `tailwind-merge`. */
export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}
