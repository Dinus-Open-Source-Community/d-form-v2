import type { IProfileProviderMeta, IProfileSectionMeta, TProfileProvider, TProfileSection } from '@/types/profile';

/** Batas ukuran avatar di frontend; backend tetap memvalidasi ukuran yang sama. */
export const PROFILE_AVATAR_MAX_SIZE_KB = 5120;

/**
 * Metadata tiap bagian halaman profil. Urutan tampilan ditentukan komposisi
 * halaman, bukan urutan objek ini. `anchorId` dipakai sebagai `id` seksi sehingga
 * redirect callback OAuth ke `#koneksi` tetap bekerja tanpa navigasi tab.
 */
export const PROFILE_SECTIONS: Readonly<Record<TProfileSection, IProfileSectionMeta>> = {
    identity: {
        key: 'identity',
        label: 'Profil',
        description: 'Foto, nama, dan email yang tampil di akun Anda.',
        anchorId: 'profil',
    },
    security: {
        key: 'security',
        label: 'Keamanan',
        description: 'Kata sandi untuk masuk tanpa Google atau GitHub.',
        anchorId: 'keamanan',
    },
    connections: {
        key: 'connections',
        label: 'Koneksi akun',
        description:
            'Masuk lebih cepat dengan Google atau GitHub. Menghubungkan tidak mengubah nama, email, atau foto Anda.',
        anchorId: 'koneksi',
    },
};

/** Label provider OAuth yang bisa dihubungkan; status dan aksi dirender per baris. */
export const PROFILE_PROVIDER_META: Readonly<Record<TProfileProvider, IProfileProviderMeta>> = {
    google: {
        label: 'Google',
    },
    github: {
        label: 'GitHub',
    },
};
