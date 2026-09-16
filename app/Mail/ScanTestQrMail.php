<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Digest QR untuk pengujian scan: satu email berisi banyak PNG QR sebagai lampiran.
 *
 * Sengaja memakai `htmlString` alih-alih Blade view: semua view di
 * `resources/views/mail/` meng-include `mail.partials.card-header`, yang saat ini
 * gagal dirender karena komponen Livewire-nya sudah dihapus dari dependensi.
 * Dengan begitu email ini tetap bisa terkirim tanpa memperbaiki view lama.
 *
 * Catatan: properti data dinamai `$qrFiles`, bukan `$attachments`, karena
 * `Illuminate\Mail\Mailable` sudah memiliki properti `public $attachments`
 * (tanpa tipe) — mendeklarasikan ulang dengan tipe akan fatal error.
 */
class ScanTestQrMail extends Mailable
{
    use Queueable;

    /**
     * @param  array<string, string>  $qrFiles  nama berkas => isi biner PNG
     */
    public function __construct(
        public string $label,
        public array $qrFiles,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Scan test QR — '.$this->label.' ('.count($this->qrFiles).' kode)',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>Lampiran berisi '.count($this->qrFiles).' QR '.e($this->label).'.</p>'
                .'<p>Nama berkas memuat kode yang bisa diketik manual pada halaman global scan.</p>',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->qrFiles as $filename => $binary) {
            $attachments[] = Attachment::fromData(static fn (): string => $binary, $filename)
                ->withMime('image/png');
        }

        return $attachments;
    }
}
