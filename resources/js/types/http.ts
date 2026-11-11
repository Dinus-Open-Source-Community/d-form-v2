/** HTTP method yang dipakai adapter transport frontend. */
export type THttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

/** Bag error validasi Laravel: satu field bisa punya satu pesan atau daftar pesan. */
export type TValidationErrorBag = Record<string, string | string[]>;

/**
 * Progres transfer byte untuk unggah/unduh. `total` 0 berarti ukuran tidak
 * diketahui, sehingga `percent` juga 0 (UI memakai mode indeterminate).
 */
export interface ITransferProgress {
    loaded: number;
    total: number;
    percent: number;
}

/** Opsi transfer opsional yang diteruskan adapter upload ke pemanggil. */
export interface IUploadTransferOptions {
    signal?: AbortSignal;
    /** Persentase 0..100, kontrak `TUploader` di `useUploadedFileState`. */
    onProgress?: (percent: number) => void;
}

/**
 * Kontrak error seragam untuk seluruh request HTTP frontend.
 * `status` tetap disimpan agar UI bisa memberi pesan spesifik per kode.
 */
export type THttpErrorKind =
    | 'network'
    | 'aborted'
    | 'timeout'
    | 'validation'
    | 'unauthorized'
    | 'forbidden'
    | 'not_found'
    | 'conflict'
    | 'rate_limited'
    | 'client'
    | 'server'
    | 'invalid_response'
    | 'unknown';
