@extends('mail.layouts.base')

@section('mail_title', 'Kehadiran kelas dikonfirmasi')

@section('mail_card')
    @include('mail.partials.card-header', [
        'eyebrow' => 'Kehadiran',
        'accent' => '#059669',
        'headline' => $event->title,
        'formTitle' => $form->title,
    ])
    <tr>
        <td style="padding:24px 32px 0;">
            <p style="margin:0 0 18px;font-size:16px;line-height:1.65;color:#374151;"><strong style="color:#111827;">Halo {{ $recipientName }},</strong></p>
            <p style="margin:0;padding:16px 18px;font-size:16px;line-height:1.65;color:#14532d;background-color:#f0fdf4;border-radius:12px;border:1px solid #bbf7d0;">Kehadiran Anda untuk kelas ini telah tercatat.</p>
        </td>
    </tr>
    <tr>
        <td style="padding:24px 32px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-size:15px;line-height:1.55;color:#374151;">
                <tr><td style="padding:10px 0;border-top:1px solid #f3f4f6;color:#6b7280;width:42%;">Tanggal kelas</td><td style="padding:10px 0;border-top:1px solid #f3f4f6;">{{ $attendance->formSession?->session_date?->format('d-m-Y') }}</td></tr>
                <tr><td style="padding:10px 0;border-top:1px solid #f3f4f6;color:#6b7280;">Pertemuan tersisa</td><td style="padding:10px 0;border-top:1px solid #f3f4f6;">{{ $remaining }} dari {{ $total }}</td></tr>
            </table>
        </td>
    </tr>
    <tr><td style="padding:0 32px 28px;"><p style="margin:0;font-size:14px;line-height:1.55;color:#6b7280;">Tidak ada tindakan lain yang perlu dilakukan.</p></td></tr>
@endsection
