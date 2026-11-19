import type { ComputedRef, Ref } from 'vue';
import type { InertiaForm } from '@inertiajs/vue3';

/** Bagian halaman profil; urutannya mengikuti tampilan dari atas ke bawah. */
export type TProfileSection = 'identity' | 'security' | 'connections';

/** Provider OAuth yang bisa dihubungkan ke akun. */
export type TProfileProvider = 'google' | 'github';

/** Satu baris koneksi provider; seluruh nilainya dihitung server. */
export interface IProfileConnection {
    provider: TProfileProvider;
    connected: boolean;
    can_disconnect: boolean;
    configured: boolean;
}

/** Prop Inertia halaman profil. */
export interface IProfilePageProps {
    connections: IProfileConnection[];
}

/** Meta satu bagian: judul, penjelasan, dan anchor deep link. */
export interface IProfileSectionMeta {
    key: TProfileSection;
    label: string;
    description: string;
    /** `id` elemen seksi; `koneksi` juga menjadi tujuan redirect callback OAuth. */
    anchorId: string;
}

/** Meta tampilan satu provider OAuth. */
export interface IProfileProviderMeta {
    label: string;
}

/** Isi form identitas: hanya field yang dikirim PATCH /profile. */
export interface IProfileIdentityFormValues {
    name: string;
    email: string;
}

/** Isi form kata sandi; `current_password` hanya relevan untuk akun ber-kata sandi. */
export interface IProfilePasswordFormValues {
    current_password: string;
    password: string;
    password_confirmation: string;
}

/** Field kata sandi pada bagian Keamanan; kuncinya dipakai status visibilitas. */
export type TProfilePasswordField = 'current' | 'next' | 'confirm';

/** Visibilitas tiap field kata sandi; masing-masing punya tombol mata sendiri. */
export interface IProfilePasswordVisibility {
    current: Ref<boolean>;
    next: Ref<boolean>;
    confirm: Ref<boolean>;
}

/** Form identitas beserta status dirty dan error siap tampil. */
export interface IProfileIdentityForm {
    form: InertiaForm<IProfileIdentityFormValues>;
    isDirty: ComputedRef<boolean>;
    /** Pesan error `name`/`email` yang sudah di-humanize; key kosong dibuang. */
    errors: ComputedRef<Record<string, string>>;
    submit: () => void;
}

/** Form kata sandi beserta status dirty dan visibilitas field. */
export interface IProfilePasswordForm {
    form: InertiaForm<IProfilePasswordFormValues>;
    isDirty: ComputedRef<boolean>;
    hasLocalPassword: ComputedRef<boolean>;
    /** Visibilitas per field: kata sandi saat ini, baru, dan konfirmasi berdiri sendiri. */
    visibility: IProfilePasswordVisibility;
    toggleVisibility: (field: TProfilePasswordField) => void;
    submit: () => void;
}

/** Kontrol avatar: berkas tertunda, pratinjau, unggah/hapus, dan progres byte. */
export interface IProfileAvatar {
    /** URL tampilan: pratinjau berkas tertunda, URL server, atau `null` saat ditandai hapus. */
    previewUrl: ComputedRef<string | null>;
    isPicked: ComputedRef<boolean>;
    isRemovalPending: ComputedRef<boolean>;
    isProcessing: ComputedRef<boolean>;
    isDirty: ComputedRef<boolean>;
    /** Persentase unggah nyata 0..100; `null` saat tidak ada unggahan berjalan. */
    progress: Ref<number | null>;
    /** Pesan gagal pilih berkas atau error validasi server untuk field `avatar`. */
    errorMessage: ComputedRef<string>;
    pick: (file: File) => Promise<void>;
    /** Kirim berkas tertunda atau hapus avatar; tidak melakukan apa pun tanpa perubahan. */
    save: () => void;
    /** Tandai avatar server untuk dihapus pada `save()` berikutnya. */
    remove: () => void;
    /** Buang berkas tertunda dan pratinjaunya. */
    cancel: () => void;
}
