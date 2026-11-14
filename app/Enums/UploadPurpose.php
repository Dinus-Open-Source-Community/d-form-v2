<?php

namespace App\Enums;

/** Kebijakan unggahan per tujuan pemakaian: daftar MIME hasil sniff, ekstensi yang boleh, batas ukuran, dan disk tujuan. */
enum UploadPurpose: string
{
    /** Gambar raster publik: banner event/period, avatar, gambar form, inline broadcast. */
    case Image = 'image';

    /** Bukti follow Instagram (tanpa GIF) pada form OPRec. */
    case InstagramProof = 'instagram_proof';

    /** Lampiran jawaban form (PDF, Office Open XML, teks, atau gambar), disk lokal. */
    case FormAnswerFile = 'form_answer_file';

    /** Lampiran email broadcast (PDF atau gambar), disk lokal. */
    case BroadcastAttachment = 'broadcast_attachment';

    /** CV pelamar OPRec: PDF saja. */
    case RecruitmentCv = 'recruitment_cv';

    /** Berkas portfolio pelamar OPRec: PDF saja. */
    case RecruitmentPortfolio = 'recruitment_portfolio';

    /** CSV/TXT dataset broadcast; hanya diparse, tidak disimpan. */
    case DatasetCsv = 'dataset_csv';

    /** @return list<string> */
    public function mimes(): array
    {
        return match ($this) {
            self::Image => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            self::InstagramProof => ['image/jpeg', 'image/png', 'image/webp'],
            self::FormAnswerFile => [
                'application/pdf',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/csv',
                'text/plain',
                'application/zip',
                'image/jpeg',
                'image/png',
                'image/webp',
                'image/gif',
            ],
            self::BroadcastAttachment => ['application/pdf', 'image/jpeg', 'image/png'],
            self::RecruitmentCv, self::RecruitmentPortfolio => ['application/pdf'],
            self::DatasetCsv => ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'],
        };
    }

    /** @return list<string> */
    public function extensions(): array
    {
        return match ($this) {
            self::Image => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
            self::InstagramProof => ['jpg', 'jpeg', 'png', 'webp'],
            self::FormAnswerFile => ['pdf', 'docx', 'xlsx', 'pptx', 'csv', 'txt', 'jpg', 'jpeg', 'png', 'webp', 'gif'],
            self::BroadcastAttachment => ['pdf', 'jpg', 'jpeg', 'png'],
            self::RecruitmentCv, self::RecruitmentPortfolio => ['pdf'],
            self::DatasetCsv => ['csv', 'txt'],
        };
    }

    /** Batas ukuran byte. */
    public function maxBytes(): int
    {
        return match ($this) {
            self::DatasetCsv => max(1, (int) config('events.forms.dataset_csv_max_size_kb', 2048)) * 1024,
            default => max(1, (int) config('events.forms.upload_max_size_kb', 5120)) * 1024,
        };
    }

    public function maxSizeKb(): int
    {
        return intdiv($this->maxBytes(), 1024);
    }

    public function disk(): string
    {
        return match ($this) {
            self::Image => 'public',
            default => 'local',
        };
    }
}
