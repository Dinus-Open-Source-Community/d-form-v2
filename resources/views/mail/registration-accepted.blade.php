@extends('mail.layouts.base')

@section('mail_title', __('Registration accepted'))

@section('mail_card')
    @include('mail.partials.card-header', [
        'eyebrow' => __('Accepted'),
        'accent' => '#059669',
        'headline' => $event->title,
        'formTitle' => $form->title,
    ])
    <tr>
        <td style="padding:24px 32px 0;">
            <p style="margin:0 0 18px;font-size:16px;line-height:1.65;color:#374151;">
                <strong style="color:#111827;">{{ __('Hello') }} {{ $user->name }},</strong>
            </p>
            <p style="margin:0;font-size:16px;line-height:1.65;color:#374151;">
                {{ __('Great news — your registration has been accepted. Use the QR code or manual code below for check-in at the event.') }}
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding:24px 32px;">
            <p style="margin:0 0 14px;font-size:13px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">{{ __('Event details') }}</p>
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-size:15px;line-height:1.55;color:#374151;">
                <tr>
                    <td style="padding:10px 0;border-top:1px solid #f3f4f6;color:#6b7280;width:39%;">{{ __('Location') }}</td>
                    <td style="padding:10px 0;border-top:1px solid #f3f4f6;">{{ $event->location }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 0;border-top:1px solid #f3f4f6;color:#6b7280;">{{ __('Event dates') }}</td>
                    <td style="padding:10px 0;border-top:1px solid #f3f4f6;">{{ $event->start_date->format('Y-m-d') }} — {{ $event->end_date->format('Y-m-d') }}</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="padding:0 32px 28px;text-align:center;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background-color:#f9fafb;border-radius:14px;border:1px solid #f3f4f6;">
                <tr>
                    <td style="padding:24px 24px;">
                        <p style="margin:0 0 8px;font-size:13px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">{{ __('Attendance QR code') }}</p>
                        <p style="margin:0 0 18px;font-size:14px;line-height:1.55;color:#6b7280;">{{ __('Show this at check-in for') }} {{ $event->title }}</p>
                        <img src="data:image/png;base64,{{ $qrBase64 }}" alt="{{ __('Attendance QR code') }}" width="240" height="240" style="display:block;margin:0 auto;border:1px solid #e5e7eb;border-radius:12px;background-color:#ffffff;">
                        <p style="margin:24px 0 8px;font-size:13px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">{{ __('Manual registration code') }}</p>
                        <p style="margin:0 0 10px;font-size:14px;color:#6b7280;line-height:1.55;">{{ __('If scanning fails, tell staff this code:') }}</p>
                        <p style="margin:0;padding:14px 18px;font-size:22px;font-weight:700;letter-spacing:0.06em;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;color:#111827;background-color:#ffffff;border-radius:10px;border:1px dashed #e5e7eb;">{{ $registrationCode }}</p>
                        <p style="margin:18px 0 0;font-size:12px;color:#9ca3af;">{{ __('Submission ID') }}: {{ $submission->id }}</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    @include('mail.partials.registration-details-button')
    @include('mail.partials.card-footer-by-app')
@endsection
