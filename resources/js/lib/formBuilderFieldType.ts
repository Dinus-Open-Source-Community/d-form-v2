/**
 * Tipe field yang dikenali form builder. Record (bukan array) agar TypeScript
 * mewajibkan seluruh anggota `TFormBuilderType` terdaftar — daftar runtime tidak
 * bisa tertinggal dari union tipe.
 */
const BUILDER_FIELD_TYPE_FLAGS: Record<TFormBuilderType, true> = {
    short_text: true,
    long_text: true,
    email: true,
    phone: true,
    number: true,
    time: true,
    dropdown: true,
    checkbox: true,
    radio: true,
    image_upload: true,
    file_upload: true,
    date: true,
    rating: true,
    heading: true,
    paragraph: true,
    divider: true,
    banner: true,
    confirmation: true,
    image: true,
    url: true,
    address: true,
    yes_no: true,
};

/**
 * Guard tipe field builder untuk nilai dinamis (payload drag, JSON eksternal).
 * Nilai di luar daftar ditolak agar field yang tidak bisa dirender tidak dibuat.
 */
export function isFormBuilderFieldType(value: unknown): value is TFormBuilderType {
    return typeof value === 'string' && Object.hasOwn(BUILDER_FIELD_TYPE_FLAGS, value);
}
