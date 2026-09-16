<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Digest QR untuk pengujian scan: satu email berisi daftar kode + banyak PNG QR
 * sebagai lampiran.
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
     * @param  array<int, array{code:string,name:string,kind:string,context:string}>  $rows  daftar kode yang dikirim
     * @param  array<string, string>  $qrFiles  nama berkas => isi biner PNG
     */
    public function __construct(
        public string $label,
        public array $rows,
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
            htmlString: $this->buildHtml(),
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

    private function buildHtml(): string
    {
        $cell = 'padding:4px 8px;border:1px solid #d4d4d8;text-align:left;vertical-align:top';

        $head = '';
        foreach (['Kode', 'Nama', 'Jenis', 'Event / Periode'] as $heading) {
            $head .= '<th style="'.$cell.';background:#f4f4f5">'.e($heading).'</th>';
        }

        $body = '';
        foreach ($this->rows as $row) {
            $body .= '<tr>'
                .'<td style="'.$cell.';font-family:ui-monospace,SFMono-Regular,Menlo,monospace">'.e($row['code']).'</td>'
                .'<td style="'.$cell.'">'.e($row['name']).'</td>'
                .'<td style="'.$cell.'">'.e($row['kind']).'</td>'
                .'<td style="'.$cell.'">'.e($row['context']).'</td>'
                .'</tr>';
        }

        if ($body === '') {
            $body = '<tr><td style="'.$cell.'" colspan="4">Tidak ada baris.</td></tr>';
        }

        return '<p>Lampiran berisi '.count($this->qrFiles).' QR '.e($this->label).'.</p>'
            .'<table style="border-collapse:collapse;font-size:13px;font-family:system-ui,sans-serif">'
            .'<thead><tr>'.$head.'</tr></thead>'
            .'<tbody>'.$body.'</tbody>'
            .'</table>'
            .'<p>Nama berkas lampiran memakai kode di atas, jadi bisa juga diketik manual pada halaman global scan.</p>';
    }
}
