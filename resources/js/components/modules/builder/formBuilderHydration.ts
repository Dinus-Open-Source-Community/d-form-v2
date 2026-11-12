/**
 * Pemetaan snapshot form dari server (header + daftar field) menjadi state builder.
 * Satu implementasi untuk halaman edit form dan draf halaman create, supaya keduanya
 * menghidrasi model dengan aturan yang sama: urutan field, field banner sintetis,
 * metadata registrasi, dan fallback header kosong.
 *
 * Ditempatkan di `components/modules/builder/` (bukan `lib/`) karena pemetaannya
 * memakai `fieldMapping` dan `formBanner`; `lib/` dilarang mengimpor layer komponen.
 */

import { fromBackendField, type IBackendField } from './fieldMapping';
import { extractFormBannerFromBuilderFields, type IFormBannerState } from './formBanner';
import type { IBuilderField } from '@/types/form-builder';
import type { IFormRegistrationMetadata } from '@/types/form';
import { parseFormRegistrationMetadata } from '@/lib/formRegistrationMetadata';

/** Header form seperti yang dikirim `formToInertia()`; field opsional untuk data legacy. */
export interface IFormBuilderHydrationHeader {
    title: string;
    description: string;
    success_content?: string | null;
    closed_at?: string | null;
    visible_for: readonly string[];
    banner_url?: string | null;
    banner_caption?: string | null;
    /** Metadata bisa `null`, array, atau objek (data lama). */
    metadata?: unknown;
}

export interface IFormBuilderHydrationInput {
    form: IFormBuilderHydrationHeader;
    fields: readonly IBackendField[];
}

/** State builder hasil hidrasi, siap dipetakan ke model ref halaman. */
export interface IFormBuilderHydrationResult {
    title: string;
    description: string;
    successContent: string;
    closedAt: string;
    visibleFor: string[];
    banner: IFormBannerState;
    metadata: IFormRegistrationMetadata;
    /** Field kanvas (field banner sintetis sudah dipindahkan ke `banner`). */
    fields: IBuilderField[];
}

/**
 * Normalisasi `closed_at` ke format input `datetime-local` (`Y-m-dTH:i`).
 * API mengirim `Y-m-d\TH:i` tanpa detik/zona; nilai lain dengan detik/zona
 * dipotong agar model builder tidak menerima string yang tidak valid untuk input.
 */
export function normalizeClosedAt(value: string | null | undefined): string {
    const raw = (value ?? '').trim();
    if (raw === '') return '';

    const match = /^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})/.exec(raw);

    return match === null ? '' : `${match[1]}T${match[2]}`;
}

/** Salin field backend + metadata-nya agar pemanggil bebas mengurutkan/mengubah. */
function cloneBackendFields(fields: readonly IBackendField[]): IBackendField[] {
    return fields.map((field): IBackendField => ({
        ...field,
        metadata: { ...field.metadata },
    }));
}

/**
 * Hidrasi state builder dari snapshot server. Draf kosong (judul dan field kosong)
 * menghasilkan state default, bukan error: halaman tetap tampil seperti kunjungan pertama.
 */
export function hydrateFormBuilderState(input: IFormBuilderHydrationInput): IFormBuilderHydrationResult {
    const ordered = cloneBackendFields(input.fields).sort((a, b) => a.order - b.order);
    const mapped = ordered.map((field): IBuilderField => fromBackendField(field));
    const { banner: syntheticBanner, canvasFields } = extractFormBannerFromBuilderFields(mapped);

    const banner: IFormBannerState = {
        id: syntheticBanner.id,
        bannerUrl: input.form.banner_url ?? syntheticBanner.bannerUrl,
        bannerFileName: syntheticBanner.bannerFileName,
        caption: input.form.banner_caption ?? syntheticBanner.caption,
    };

    return {
        title: input.form.title,
        description: input.form.description,
        successContent: input.form.success_content ?? '',
        closedAt: normalizeClosedAt(input.form.closed_at),
        visibleFor: [...input.form.visible_for],
        banner,
        metadata: parseFormRegistrationMetadata(input.form.metadata),
        fields: canvasFields,
    };
}
