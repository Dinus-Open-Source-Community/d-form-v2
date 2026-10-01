<?php

namespace App\Mail;

use App\Models\Broadcast;
use App\Models\BroadcastAttachment;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BroadcastMail extends Mailable
{
    public function __construct(
        public Broadcast $broadcast,
        public string $recipientEmail,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->broadcast->subject ?? $this->broadcast->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->personalizedBody(),
        );
    }

    /** Lampirkan file broadcast (pdf/jpg/png) ke email keluar. */
    public function attachments(): array
    {
        return $this->broadcast->attachments
            ->map(fn (BroadcastAttachment $attachment): Attachment => Attachment::fromStorage($attachment->path)
                ->as($attachment->original_name)
                ->withMime($attachment->mime_type ?? 'application/octet-stream'))
            ->all();
    }

    /** Ganti placeholder {{nama}} dengan nama penerima snapshot. */
    private function personalizedBody(): string
    {
        $name = $this->recipientName() ?? 'Peserta';

        return str_replace('{{nama}}', e($name), (string) $this->broadcast->body_html);
    }

    /** Cari nama penerima di snapshot berdasarkan email. */
    private function recipientName(): ?string
    {
        $recipients = $this->broadcast->recipient_snapshot['recipients'] ?? [];

        foreach ($recipients as $recipient) {
            if (($recipient['email'] ?? null) === $this->recipientEmail) {
                $name = $recipient['name'] ?? null;

                return is_string($name) && trim($name) !== '' ? $name : null;
            }
        }

        return null;
    }
}
