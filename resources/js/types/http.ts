/** HTTP method yang dipakai adapter transport frontend. */
export type THttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

/** Bag error validasi Laravel: satu field bisa punya satu pesan atau daftar pesan. */
export type TValidationErrorBag = Record<string, string | string[]>;

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
