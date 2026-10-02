<?php

namespace App\Mail;

use App\Models\EmailBroadcastAttachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Storage;

/**
 * Email broadcast generik — memakai konfigurasi mail global DForm.
 * Attachment diambil dari storage lokal (disk `local`).
 */
class BroadcastMail extends Mailable
{
    public function __construct(
        public string $subjectLine,
        public string $htmlBody,
        public string $textBody,
        public string $broadcastId,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.broadcast-html',
            text: 'mail.broadcast-text',
            with: [
                'htmlBody' => $this->htmlBody,
                'subjectLine' => $this->subjectLine,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = EmailBroadcastAttachment::query()
            ->where('broadcast_id', $this->broadcastId)
            ->get();

        $out = [];

        foreach ($attachments as $attachment) {
            $path = $attachment->file_path;
            $mime = $attachment->mime_type ?: 'application/octet-stream';

            if (! Storage::disk('local')->exists($path)) {
                continue;
            }

            $out[] = Attachment::fromStorageDisk('local', $path)
                ->as($attachment->file_name)
                ->withMime($mime);
        }

        return $out;
    }

    public static function textFallback(string $html): string
    {
        $text = strip_tags(preg_replace('#<(br|p|div|li|tr)[^>]*>#i', "\n\$0", $html) ?? '');

        return html_entity_decode(trim($text), ENT_QUOTES, 'UTF-8');
    }
}
