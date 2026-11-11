/**
 * Bentuk data autosave form builder, dipisah per domain:
 * - **Header** (judul + deskripsi) — sinkron sendiri saat keduanya berubah.
 * - **Fields** (daftar field saja) — `POST {form}/fields`, tanpa data header.
 *
 * Konsekuensi pemisahan ketat ini: **struktur section tidak pernah di-autosave**
 * (kanal fields tidak membawa `metadata`); section hanya tersimpan lewat simpan
 * manual/publish yang mengirim payload lengkap.
 *
 * Murni: tanpa ref, DOM, atau network. Pemetaan builder → payload backend
 * (`toBackendFields`) tetap di hook pemilik siklus builder karena `lib/` tidak boleh
 * mengimpor layer komponen.
 */

import { cleanStoredImageUrl } from '@/lib/builderImageSanitizer';
import { toFormMetadataPayload } from '@/lib/formRegistrationMetadata';
import type { IBuilderField } from '@/types/form-builder';
import type { IFormRegistrationMetadata } from '@/types/form';

/** Bagian state banner yang ikut payload (cukup dua nilai ini). */
export interface IFormBuilderBannerState {
    bannerUrl: string;
    caption: string;
}

/** State builder yang dibaca tepat saat payload dibangun (bukan salinan reaktif). */
export interface IFormBuilderDraftState {
    title: string;
    description: string;
    successContent: string;
    closedAt: string;
    visibleFor: readonly string[];
    banner: IFormBuilderBannerState;
    fields: readonly IBuilderField[];
    metadata: IFormRegistrationMetadata;
}

export interface IFormBuilderSettingsPayloadOptions {
    /** Payload field backend yang sudah dipetakan pemanggil. */
    fields?: unknown[];
    /** `true` saat tombol simpan ditekan (draf → published). */
    publish?: boolean;
    /** `true` untuk autosave draf: backend memvalidasi hanya key yang dikirim. */
    autosave?: boolean;
}

export interface IFormBuilderAutosaveUrls {
    fieldsUrl: string;
    settingsUrl: string;
}

/** Bentuk payload `pagehide`; struktural sama dengan `IFormSnapshotKeepaliveOptions` adapter. */
export interface IFormBuilderKeepalivePayload {
    fieldsUrl?: string;
    fields?: unknown[];
    settingsUrl?: string;
    settings?: Record<string, unknown>;
}

/** Header dianggap lengkap bila backend (mode full) tidak akan menolak PUT settings. */
export function isFormBuilderHeaderComplete(state: IFormBuilderDraftState): boolean {
    return (
        state.title.trim() !== '' &&
        state.description.trim() !== '' &&
        state.closedAt.trim() !== '' &&
        state.visibleFor.length > 0
    );
}

/**
 * Snapshot kanal header: hanya judul + deskripsi, sehingga perubahan closed_at,
 * visibilitas, banner, atau section tidak memicu simpan header.
 */
export function serializeHeaderSnapshot(state: IFormBuilderDraftState): string {
    return JSON.stringify({ title: state.title, description: state.description });
}

/**
 * Snapshot kanal fields: **hanya** daftar field. Perubahan header maupun section
 * tidak memicu kanal ini; urutan/isi/id field ikut membentuk snapshot.
 */
export function serializeFieldsSnapshot(state: IFormBuilderDraftState): string {
    return JSON.stringify({ fields: state.fields });
}

/** Payload `PUT {form}`: settings bertipe, dengan opsi `fields`/`publish`/`autosave`. */
export function buildFormBuilderSettingsPayload(
    state: IFormBuilderDraftState,
    options: IFormBuilderSettingsPayloadOptions = {}
): Record<string, unknown> {
    const payload: Record<string, unknown> = {
        title: state.title,
        description: state.description,
        success_content: state.successContent,
        closed_at: state.closedAt,
        visible_for: [...state.visibleFor],
        banner_url: cleanStoredImageUrl(state.banner.bannerUrl) || null,
        banner_caption: state.banner.caption || null,
        metadata: toFormMetadataPayload(state.metadata),
    };

    if (options.fields !== undefined) {
        payload.fields = options.fields;
    }

    if (options.publish !== undefined) {
        payload.publish = options.publish;
    }

    if (options.autosave !== undefined) {
        payload.autosave = options.autosave;
    }

    return payload;
}

/** Payload header ringkas untuk kanal header (mode draf parsial). */
export function buildHeaderAutosavePayload(state: IFormBuilderDraftState): Record<string, unknown> {
    return { title: state.title, description: state.description, autosave: true };
}

/**
 * Payload kanal header untuk `pagehide`: draf memakai header ringkas, form published
 * memakai payload lengkap (mode full mewajibkan seluruh header).
 */
export function buildHeaderKeepalivePayload(
    state: IFormBuilderDraftState,
    urls: IFormBuilderAutosaveUrls,
    options: { isDraft: boolean }
): IFormBuilderKeepalivePayload {
    if (options.isDraft) {
        return { settingsUrl: urls.settingsUrl, settings: buildHeaderAutosavePayload(state) };
    }

    if (!isFormBuilderHeaderComplete(state)) return {};

    return { settingsUrl: urls.settingsUrl, settings: buildFormBuilderSettingsPayload(state) };
}

/**
 * Payload kanal fields untuk `pagehide`: hanya daftar field. Daftar kosong tetap
 * dikirim (penghapusan field terakhir masih tertunda), bukan membawa metadata/section.
 */
export function buildFieldsKeepalivePayload(
    urls: IFormBuilderAutosaveUrls,
    fields: unknown[]
): IFormBuilderKeepalivePayload {
    return { fieldsUrl: urls.fieldsUrl, fields };
}
