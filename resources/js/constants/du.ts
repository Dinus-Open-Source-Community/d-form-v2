/**
 * Pesan gagal katalog DU. Satu sumber untuk hook katalog (inline), hook sync
 * (inline + toast), dan test agar copy tidak menyimpang antar layer.
 */
export const DU_CATALOG_ERROR_MESSAGE = 'Gagal memuat katalog kelas DU. Coba lagi.';

/** Workspace menolak hapus field yang masih menjadi sumber redeem DU. */
export const DU_FIELD_DELETE_BLOCKED_MESSAGE = 'Field kelas DU tidak dapat dihapus. Matikan integrasi DU dulu.';

/** Gagal menyimpan mapping opsi kelas dari drawer jawaban. */
export const DU_MAPPING_SAVE_ERROR_MESSAGE = 'Gagal menyimpan mapping kelas DU. Coba lagi.';

/** Gagal menerbitkan kode redeem dari drawer jawaban. */
export const DU_ISSUE_ERROR_MESSAGE = 'Gagal menerbitkan kode DU. Coba lagi.';

/** Gagal mengirim ulang email kode yang sudah terbit. */
export const DU_RESEND_ERROR_MESSAGE = 'Gagal mengirim ulang email kode DU. Coba lagi.';

/** Status `failed` ditampilkan sebagai alasan lokal; detail backend tidak dirender. */
export const DU_ISSUE_FAILED_MESSAGE = 'Penerbitan kode gagal.';

/** Status `unknown` tidak diulang otomatis dan menunggu rekonsiliasi DU. */
export const DU_ISSUE_UNKNOWN_MESSAGE = 'Menunggu rekonsiliasi dari DU.';

/** Field sistem tidak bisa dipetakan dari drawer; nilainya harus ada di katalog DU. */
export const DU_CLASS_NOT_FOUND_MESSAGE = 'Kelas tidak ditemukan di katalog DU.';
