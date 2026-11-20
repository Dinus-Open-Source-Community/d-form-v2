import type { TIconComponent } from '@/types/icons';
import {
    AlignLeft,
    Calendar,
    ChevronDown,
    CircleDot,
    Clock,
    Hash,
    GraduationCap,
    Heading as HeadingIcon,
    IdCard,
    ImagePlus,
    Mail,
    MapPin,
    Minus,
    Phone,
    SquareCheck,
    Star,
    TextCursorInput,
    Type,
    Upload,
} from 'lucide-vue-next';

/**
 * Tone visual field memakai token warna sistem, bukan hex bebas.
 * `info` tidak dipakai karena `--info` dialias ke `--primary` (lihat app.css), sehingga
 * tone terpisah untuknya hanya akan menduplikasi tampilan tone primary.
 */
export type TFieldTone = 'neutral' | 'primary' | 'success' | 'warning';

export interface IFieldTypeMeta {
    icon: TIconComponent;
    label: string;
    tone: TFieldTone;
}

const SHORT_TEXT_META: IFieldTypeMeta = { icon: Type, label: 'Teks pendek', tone: 'neutral' };

/**
 * Satu sumber label/ikon/tone untuk 20 tipe builder.
 * Kartu kanvas dan drawer editor membaca map ini agar keduanya tidak pernah berbeda.
 */
export const FIELD_TYPE_META: Record<string, IFieldTypeMeta> = {
    short_text: SHORT_TEXT_META,
    long_text: { icon: AlignLeft, label: 'Teks panjang', tone: 'neutral' },
    email: { icon: Mail, label: 'Email', tone: 'primary' },
    phone: { icon: Phone, label: 'Telepon', tone: 'primary' },
    number: { icon: Hash, label: 'Angka', tone: 'primary' },
    nik: { icon: IdCard, label: 'NIK', tone: 'primary' },
    nim: { icon: GraduationCap, label: 'NIM', tone: 'primary' },
    postal_code: { icon: MapPin, label: 'Kode Pos', tone: 'primary' },
    dropdown: { icon: ChevronDown, label: 'Dropdown', tone: 'primary' },
    checkbox: { icon: SquareCheck, label: 'Centang', tone: 'primary' },
    radio: { icon: CircleDot, label: 'Pilihan tunggal', tone: 'primary' },
    image_upload: { icon: ImagePlus, label: 'Unggah gambar', tone: 'success' },
    file_upload: { icon: Upload, label: 'Unggah file', tone: 'success' },
    date: { icon: Calendar, label: 'Tanggal', tone: 'primary' },
    time: { icon: Clock, label: 'Waktu', tone: 'primary' },
    rating: { icon: Star, label: 'Rating', tone: 'warning' },
    heading: { icon: HeadingIcon, label: 'Judul', tone: 'neutral' },
    image: { icon: ImagePlus, label: 'Gambar', tone: 'neutral' },
    paragraph: { icon: TextCursorInput, label: 'Paragraf', tone: 'neutral' },
    divider: { icon: Minus, label: 'Pemisah', tone: 'neutral' },
};

/** Meta untuk tipe yang belum dikenal builder (payload lama / tipe baru) tanpa melempar error. */
export function resolveFieldTypeMeta(type: string): IFieldTypeMeta {
    return FIELD_TYPE_META[type] ?? SHORT_TEXT_META;
}

/** Latar + warna ikon chip tipe pada kartu dan badge drawer. */
export const FIELD_TONE_CHIP_CLASS: Record<TFieldTone, string> = {
    neutral: 'bg-muted text-muted-foreground',
    primary: 'bg-primary/10 text-primary',
    success: 'bg-success/10 text-success',
    warning: 'bg-warning/10 text-warning',
};
