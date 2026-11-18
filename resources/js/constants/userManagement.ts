/** Pilihan jumlah baris per halaman direktori pengguna; server memakai whitelist yang sama. */
export const USER_INDEX_PER_PAGE_OPTIONS: readonly number[] = [10, 20, 50, 100];

/** Panjang minimum password akun; harus sama dengan `min:8` di Store/UpdateUserRequest. */
export const USER_PASSWORD_MIN_LENGTH = 8;
