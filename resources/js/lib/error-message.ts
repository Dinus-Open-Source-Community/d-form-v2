import { toast } from 'vue-sonner'

export type ValidationErrors = Record<string, string | string[] | undefined>

export type ErrorMessageContext = {
    fields?: IFormField[]
    fieldLabels?: Record<string, string>
}

/** Label statis untuk field umum di luar form builder. */
export const STATIC_FIELD_LABELS: Record<string, string> = {
    title: 'Judul',
    description: 'Deskripsi',
    location: 'Lokasi',
    banner: 'Banner',
    start_date: 'Tanggal mulai acara',
    end_date: 'Tanggal selesai acara',
    registration_start: 'Mulai pendaftaran',
    registration_end: 'Akhir pendaftaran',
    quota: 'Kuota',
    price: 'Harga',
    session: 'Sesi',
    category: 'Kategori',
    publish: 'Terbitkan',
    closed_at: 'Tanggal tutup form',
    visible_for: 'Visibilitas',
    name: 'Nama',
    email: 'Email',
    password: 'Kata sandi',
    password_confirmation: 'Konfirmasi kata sandi',
    current_password: 'Kata sandi saat ini',
    avatar: 'Foto profil',
    team_member_emails: 'Email anggota tim',
    decline_reason: 'Alasan penolakan',
    invitation_decision: 'Keputusan undangan',
    fields: 'Field form',
}

/** Pesan backend (EN) → bahasa manusia (ID). */
const KNOWN_BACKEND_MESSAGES: Record<string, string> = {
    'This bundle form requires a team size of at least 2 in form settings.':
        'Form bundle ini membutuhkan ukuran tim minimal 2 di pengaturan form.',
    'This team form requires a team size of at least 2 in form settings.':
        'Form tim ini membutuhkan ukuran tim minimal 2 di pengaturan form.',
    'You cannot list yourself as a team member.':
        'Anda tidak boleh memasukkan email sendiri sebagai anggota tim.',
    'Each team member email must be unique.': 'Setiap email anggota tim harus unik.',
    'No account exists for this email. The teammate must register first.':
        'Akun untuk email ini belum ada. Anggota tim harus mendaftar terlebih dahulu.',
    'No account exists for this email.': 'Akun untuk email ini belum ada.',
    'User account found.': 'Akun pengguna ditemukan.',
    'Could not verify this email.': 'Tidak dapat memverifikasi email ini.',
    'Could not verify this email. Try again.': 'Tidak dapat memverifikasi email ini. Coba lagi.',
    'Enter a valid email address.': 'Masukkan alamat email yang valid.',
    'Form deleted.': 'Form berhasil dihapus.',
    'Form created successfully!': 'Form berhasil dibuat.',
    'Form and fields saved successfully.': 'Form dan field berhasil disimpan.',
    'Event has been archived.': 'Event berhasil diarsipkan.',
    'Event has been restored.': 'Event berhasil dipulihkan.',
    'Login success': 'Berhasil masuk.',
    'Login failed': 'Gagal masuk. Periksa email dan kata sandi.',
    'Register success': 'Pendaftaran berhasil.',
    'Register failed': 'Gagal mendaftar. Coba lagi.',
    'Unable to login with Google. Please try again.': 'Gagal masuk dengan Google. Silakan coba lagi.',
    'Unable to login with GitHub. Please try again.': 'Gagal masuk dengan GitHub. Silakan coba lagi.',
    'If an account exists for that email, we sent a password reset link.':
        'Jika akun dengan email tersebut ada, kami telah mengirim tautan reset kata sandi.',
    'Your password has been reset. You can sign in.':
        'Kata sandi berhasil diatur ulang. Anda bisa masuk sekarang.',
    'Profile updated successfully.': 'Profil berhasil diperbarui.',
    'Password updated successfully.': 'Kata sandi berhasil diperbarui.',
    'Your registration has been submitted successfully.': 'Pendaftaran Anda berhasil dikirim.',
    'Submission has already been reviewed.': 'Submission ini sudah pernah direview.',
    'Invalid review status transition.': 'Status review tidak valid.',
    'This participant must confirm their registration before it can be accepted.':
        'Peserta ini harus mengonfirmasi pendaftaran sebelum dapat diterima.',
    'Fields have been saved': 'Field berhasil disimpan.',
    'Fields cannot be saved': 'Field tidak dapat disimpan.',
    'This participant has already checked in for this event.':
        'Peserta ini sudah pernah check-in untuk event ini.',
    'Check-in queued. A confirmation email will be sent when processing completes.':
        'Check-in dalam antrean. Email konfirmasi akan dikirim setelah pemrosesan selesai.',
    'Registration not found.': 'Data pendaftaran tidak ditemukan.',
    'Registration has no participant account.':
        'Pendaftaran tidak memiliki identitas peserta (akun atau email undangan).',
    'This registration is not for this event.': 'Pendaftaran ini bukan untuk event ini.',
    'Only accepted registrations can check in.':
        'Hanya pendaftaran yang sudah diterima yang dapat check-in.',
    'Unable to read a submission ID from the QR text or registration code.':
        'Tidak dapat membaca ID submission dari QR atau kode registrasi.',
    'Participant will receive their ticket by email.':
        'Peserta akan menerima tiket melalui email.',
    'new event created successfully': 'Event baru berhasil dibuat.',
    'this event has been edited': 'Event berhasil diperbarui.',
    'event deleted successfully': 'Event berhasil dihapus.',
    'event restored successfully': 'Event berhasil dipulihkan.',
    'new form created successfully': 'Form baru berhasil dibuat.',
    'this form has been edited': 'Form berhasil diperbarui.',
    'form deleted successfully': 'Form berhasil dihapus.',
    'This form has an invalid registration configuration. Contact the organizer.':
        'Konfigurasi pendaftaran form tidak valid. Hubungi penyelenggara.',
    'This bundle form is misconfigured (team size). Contact the organizer.':
        'Form bundle tidak dikonfigurasi dengan benar (ukuran tim). Hubungi penyelenggara.',
    'This team form is misconfigured (team size). Contact the organizer.':
        'Form tim tidak dikonfigurasi dengan benar (ukuran tim). Hubungi penyelenggara.',
    'Bundle participants were misconfigured. Try again or contact the organizer.':
        'Peserta bundle tidak dikonfigurasi dengan benar. Coba lagi atau hubungi penyelenggara.',
    'A participant is already registered for this form.':
        'Salah satu peserta sudah terdaftar di form ini.',
    'A participant with this email is already registered for this form.':
        'Email peserta ini sudah terdaftar di form ini.',
    'You have already submitted this form.': 'Anda sudah mengirim form ini.',
    'This invitation has expired.': 'Undangan ini sudah kedaluwarsa.',
    'This invitation was declined.': 'Undangan ini sudah ditolak.',
    'Failed to create form.': 'Gagal membuat form.',
    'Failed to save form. Please check your fields.': 'Gagal menyimpan form. Periksa field yang ditandai.',
    'Failed to archive event.': 'Gagal mengarsipkan event.',
    'Failed to restore event.': 'Gagal memulihkan event.',
    'Unable to send reset link. Please try again.':
        'Tidak dapat mengirim tautan reset. Silakan coba lagi.',
    'Password must be at least 8 characters.': 'Kata sandi minimal 8 karakter.',
    'Password does not match.': 'Konfirmasi kata sandi tidak cocok.',
    'Password does not match': 'Konfirmasi kata sandi tidak cocok.',
    'You have declined this registration invitation.': 'Anda telah menolak undangan pendaftaran ini.',
    'Thank you — your registration is confirmed.': 'Terima kasih — pendaftaran Anda telah dikonfirmasi.',
    'Your bundle registration has been submitted. Confirmation emails were sent to all participants.':
        'Pendaftaran bundle berhasil dikirim. Email konfirmasi telah dikirim ke semua peserta.',
    'Your team registration has been submitted. Invitations were sent to team members.':
        'Pendaftaran tim berhasil dikirim. Undangan telah dikirim ke anggota tim.',
    'Gagal menyimpan. Periksa field yang ditandai lalu coba lagi.':
        'Gagal menyimpan. Periksa field yang ditandai lalu coba lagi.',
    'Terjadi kesalahan server saat memuat halaman. Coba refresh; jika tetap terjadi, ini perlu diperbaiki di server.':
        'Terjadi kesalahan server saat memuat halaman. Coba refresh; jika tetap terjadi, hubungi admin.',
}

type LaravelRulePattern = {
    test: RegExp
    build: (label: string, match: RegExpMatchArray) => string
}

const LARAVEL_EN_RULES: LaravelRulePattern[] = [
    { test: /^The .+ field is required\.?$/i, build: (l) => `${l} wajib diisi.` },
    { test: /^The .+ field must be a valid email address\.?$/i, build: (l) => `${l} harus berupa alamat email yang valid.` },
    { test: /^The .+ field must be a valid URL\.?$/i, build: (l) => `${l} harus berupa URL yang valid.` },
    { test: /^The .+ field must be a valid date\.?$/i, build: (l) => `${l} harus berupa tanggal yang valid.` },
    { test: /^The .+ field must be a date after or equal to .+\.?$/i, build: (l) => `${l} harus berupa tanggal setelah atau sama dengan batas minimum.` },
    { test: /^The .+ field must be a date before or equal to .+\.?$/i, build: (l) => `${l} harus berupa tanggal sebelum atau sama dengan batas maksimum.` },
    { test: /^The .+ field must be a file\.?$/i, build: (l) => `${l} harus berupa berkas.` },
    { test: /^The .+ field must be an array\.?$/i, build: (l) => `${l} harus berupa daftar pilihan.` },
    { test: /^The .+ field must not be greater than (\d+) kilobytes\.?$/i, build: (l, m) => `${l} ukurannya tidak boleh lebih dari ${m[1]} KB.` },
    { test: /^The .+ field must be at least (\d+) characters\.?$/i, build: (l, m) => `${l} minimal ${m[1]} karakter.` },
    { test: /^The .+ field must not be greater than (\d+) characters\.?$/i, build: (l, m) => `${l} maksimal ${m[1]} karakter.` },
    { test: /^The .+ field must be accepted\.?$/i, build: (l) => `${l} harus dicentang.` },
    { test: /^The selected .+ is invalid\.?$/i, build: (l) => `Pilihan ${l} tidak valid.` },
    { test: /^The .+ field format is invalid\.?$/i, build: (l) => `Format ${l} tidak valid.` },
    { test: /^The .+ confirmation does not match\.?$/i, build: (l) => `Konfirmasi ${l} tidak cocok.` },
]

const LARAVEL_ID_RULES: LaravelRulePattern[] = [
    { test: /^:.+ harus diisi\.?$/i, build: (l) => `${l} wajib diisi.` },
    { test: /^:.+ wajib diisi\.?$/i, build: (l) => `${l} wajib diisi.` },
]

export function normalizeErrorText(value: string | string[] | undefined | null): string {
    if (value == null) return ''
    if (Array.isArray(value)) return normalizeErrorText(value[0])
    return String(value).trim()
}

function escapeRegex(value: string): string {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

function fieldNameFromKey(key: string): string {
    const bundle = key.match(/^bundle__(.+)__(\d+)$/)
    if (bundle) return bundle[1]

    const teamEmail = key.match(/^team_member_emails\.(\d+)$/)
    if (teamEmail) return 'team_member_emails'

    return key.split('.')[0] ?? key
}

export function humanizeFieldKey(key: string, ctx?: ErrorMessageContext): string {
    if (STATIC_FIELD_LABELS[key]) return STATIC_FIELD_LABELS[key]

    const bundleMatch = key.match(/^bundle__(.+)__(\d+)$/)
    if (bundleMatch) {
        const fieldName = bundleMatch[1]
        const slot = Number.parseInt(bundleMatch[2], 10)
        const fieldLabel = resolveFieldLabel(fieldName, ctx)
        return `${fieldLabel} (Peserta ${slot + 2})`
    }

    const teamEmailMatch = key.match(/^team_member_emails\.(\d+)$/)
    if (teamEmailMatch) {
        return `Email anggota ${Number.parseInt(teamEmailMatch[1], 10) + 1}`
    }

    return resolveFieldLabel(fieldNameFromKey(key), ctx)
}

export function resolveFieldLabel(name: string, ctx?: ErrorMessageContext): string {
    if (STATIC_FIELD_LABELS[name]) return STATIC_FIELD_LABELS[name]
    if (ctx?.fieldLabels?.[name]) return ctx.fieldLabels[name]

    const field = ctx?.fields?.find((f) => f.name === name)
    if (field?.label?.trim()) return field.label.trim()

    return name.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function injectFieldLabel(message: string, key: string, label: string): string {
    const variants = new Set<string>([
        key,
        key.replace(/_/g, ' '),
        key.replace(/\./g, ' '),
        fieldNameFromKey(key),
        fieldNameFromKey(key).replace(/_/g, ' '),
    ])

    let output = message
    for (const variant of variants) {
        if (!variant || variant.length < 2) continue
        const re = new RegExp(escapeRegex(variant), 'i')
        if (re.test(output)) {
            output = output.replace(re, label)
            break
        }
    }

    return output.replace(/^:Attribute\b/i, label).replace(/^:attribute\b/i, label)
}

function translateLaravelMessage(message: string, label: string): string {
    for (const rule of LARAVEL_EN_RULES) {
        const match = message.match(rule.test)
        if (match) return rule.build(label, match)
    }

    for (const rule of LARAVEL_ID_RULES) {
        const match = message.match(rule.test)
        if (match) return rule.build(label, match)
    }

    return injectFieldLabel(message, label, label)
}

export function humanizeErrorMessage(
    raw: string | string[] | undefined,
    fieldKey?: string,
    ctx?: ErrorMessageContext,
): string {
    const message = normalizeErrorText(raw)
    if (!message) return ''

    const known = KNOWN_BACKEND_MESSAGES[message]
    if (known) return known

    const label = fieldKey ? humanizeFieldKey(fieldKey, ctx) : ''
    if (label) {
        return translateLaravelMessage(message, label)
    }

    return message
}

export function getFieldError(
    errors: ValidationErrors,
    key: string,
    ctx?: ErrorMessageContext,
): string | undefined {
    const message = normalizeErrorText(errors[key])
    if (!message) return undefined
    return humanizeErrorMessage(message, key, ctx)
}

export function parseValidationErrors(
    errors: ValidationErrors,
    ctx?: ErrorMessageContext,
): Array<{ key: string; label: string; message: string; line: string }> {
    return Object.entries(errors)
        .filter(([, value]) => normalizeErrorText(value).length > 0)
        .map(([key, raw]) => {
            const label = humanizeFieldKey(key, ctx)
            const message = humanizeErrorMessage(raw, key, ctx)
            return { key, label, message, line: `${label}: ${message}` }
        })
}

export function showValidationErrorToast(
    errors: ValidationErrors,
    ctx?: ErrorMessageContext & { title?: string },
): void {
    const parsed = parseValidationErrors(errors, ctx)
    const title = ctx?.title ?? 'Validasi gagal'

    if (parsed.length === 0) {
        toast.error(title, {
            description: 'Periksa kembali formulir dan lengkapi field yang ditandai.',
        })
        return
    }

    if (parsed.length === 1) {
        const only = parsed[0]
        toast.error(title, {
            description: `${only.label}: ${only.message}`,
            duration: 6000,
        })
        return
    }

    toast.error(title, {
        description: parsed.map((entry) => entry.line).join('\n'),
        duration: Math.min(14_000, 4000 + parsed.length * 800),
    })
}

export function handleInertiaFormErrors(
    errors: ValidationErrors,
    ctx?: ErrorMessageContext & { title?: string },
): void {
    showValidationErrorToast(errors, ctx)
}

export function parseApiErrorMessage(body: unknown, fallback = 'Terjadi kesalahan. Coba lagi.'): string {
    if (!body || typeof body !== 'object') return fallback

    const record = body as Record<string, unknown>

    if (typeof record.message === 'string' && record.message.trim()) {
        return humanizeErrorMessage(record.message)
    }

    if (record.errors && typeof record.errors === 'object') {
        const firstKey = Object.keys(record.errors as ValidationErrors)[0]
        if (firstKey) {
            const firstValue = (record.errors as ValidationErrors)[firstKey]
            const parsed = humanizeErrorMessage(firstValue, firstKey)
            if (parsed) return parsed
        }
    }

    return fallback
}

export function showHttpErrorToast(
    status: number,
    body?: unknown,
    overrides?: Partial<Record<number, string>>,
): void {
    const defaults: Record<number, string> = {
        401: 'Anda perlu masuk terlebih dahulu.',
        403: 'Anda tidak memiliki izin untuk tindakan ini.',
        404: 'Data tidak ditemukan.',
        419: 'Sesi tidak valid atau sudah habis. Muat ulang halaman lalu coba lagi.',
        422: 'Data yang dikirim tidak valid. Periksa kembali formulir.',
        429: 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.',
        500: 'Terjadi kesalahan server. Coba lagi nanti.',
        503: 'Layanan sedang sibuk. Coba lagi nanti.',
        ...overrides,
    }

    const fromBody = body ? parseApiErrorMessage(body, '') : ''
    if (fromBody) {
        toast.error(fromBody)
        return
    }

    toast.error(defaults[status] ?? `Permintaan gagal (kode ${status}).`)
}

export function showErrorToast(
    message: string,
    options?: { title?: string; description?: string; duration?: number },
): void {
    const text = humanizeErrorMessage(message)

    if (options?.title) {
        toast.error(options.title, {
            description: text || options.description,
            duration: options.duration,
        })
        return
    }

    toast.error(text, {
        description: options?.description,
        duration: options?.duration,
    })
}

export function showFlashToast(flash: { type?: string; message?: string } | null | undefined): void {
    if (!flash?.message) return

    const message = humanizeErrorMessage(flash.message)
    if (flash.type === 'success') {
        toast.success(message)
        return
    }

    toast.error(message)
}

/** @deprecated Gunakan {@link showValidationErrorToast} */
export function showEventValidationToast(errors: ValidationErrors): void {
    showValidationErrorToast(errors, { title: 'Validasi gagal' })
}

export function buildFieldLabelMap(fields: IFormField[]): Record<string, string> {
    const map: Record<string, string> = {}
    for (const field of fields) {
        if (field.label?.trim()) {
            map[field.name] = field.label.trim()
        }
    }
    return map
}
