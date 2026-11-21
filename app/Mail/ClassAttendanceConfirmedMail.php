<?php

namespace App\Mail;

use App\Models\EventAttendance;
use App\Models\FormAnswer;
use App\Services\Registration\RegistrationNotificationMailData;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClassAttendanceConfirmedMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public FormAnswer $submission,
        public EventAttendance $attendance,
        public int $remaining,
        public int $total,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Kehadiran kelas dikonfirmasi: '.$this->submission->form->event->title);
    }

    public function content(): Content
    {
        $mailData = app(RegistrationNotificationMailData::class)->shared($this->submission);

        return new Content(
            html: 'mail.class-attendance-confirmed',
            text: 'mail.class-attendance-confirmed-text',
            with: array_merge($mailData, [
                'attendance' => $this->attendance,
                'remaining' => $this->remaining,
                'total' => $this->total,
            ]),
        );
    }
}
