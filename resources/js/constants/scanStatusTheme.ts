import type { Component } from 'vue';
import { AlertTriangle, CheckCircle, XCircle } from 'lucide-vue-next';
import type { TScanStatus } from '@/lib/qrScanUi';

/** Tampilan (ikon, warna, label) hasil scan QR per status. Runtime constant tanpa behavior. */
export const SCAN_STATUS_THEME: Record<TScanStatus, { icon: Component; class: string; bg: string; label: string }> = {
    success: { icon: CheckCircle, class: 'text-success', bg: 'bg-success/10', label: 'Check-in berhasil' },
    already: { icon: AlertTriangle, class: 'text-warning', bg: 'bg-warning/10', label: 'Sudah pernah scan' },
    invalid: { icon: XCircle, class: 'text-destructive', bg: 'bg-destructive/10', label: 'QR tidak valid' },
};
