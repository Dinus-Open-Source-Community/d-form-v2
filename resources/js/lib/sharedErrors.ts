/** Bag error Inertia bersama: satu field bisa punya satu pesan atau daftar pesan. */
type TSharedErrorBag = Record<string, string | string[]>

/** Guard bag error dari props Inertia; nilai non-objek dianggap tidak ada. */
function isSharedErrorBag(value: unknown): value is TSharedErrorBag {
    return typeof value === 'object' && value !== null && !Array.isArray(value)
}

/**
 * Baca pesan error yang dikirim server di luar field form (mis. `application`,
 * `credentials`), yang tidak tercakup tipe `FormDataErrors` milik `useForm`.
 */
export function readSharedError(props: { errors?: unknown }, key: string): string | undefined {
    if (!isSharedErrorBag(props.errors)) {
        return undefined
    }

    const message = props.errors[key]

    return typeof message === 'string' ? message : undefined
}
