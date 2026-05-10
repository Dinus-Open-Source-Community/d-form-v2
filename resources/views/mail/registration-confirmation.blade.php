@extends('mail.layouts.base')

@section('mail_title', __('Registration received'))

@section('mail_card')
    @include('mail.partials.card-header', [
        'eyebrow' => __('Registration'),
        'accent' => '#4f46e5',
        'headline' => $event->title,
        'formTitle' => $form->title,
    ])
    <tr>
        <td style="padding:24px 32px 0;">
            <p style="margin:0 0 18px;font-size:16px;line-height:1.65;color:#374151;">
                <strong style="color:#111827;">{{ __('Hello') }} {{ $user->name }},</strong>
            </p>
            <p style="margin:0 0 18px;font-size:16px;line-height:1.65;color:#374151;">
                {{ __('Thank you — we have received your registration. Our team will review it and you will receive another email once a decision has been made.') }}
            </p>
            @if(!$showAttendanceQr)
                <p style="margin:0;padding:14px 16px;font-size:15px;line-height:1.6;color:#1e40af;background-color:#eff6ff;border-radius:10px;border:1px solid #bfdbfe;">
                    {{ __('This email does not include an attendance QR code. Once an administrator has approved your registration, you will receive another email with your QR code for event check-in.') }}
                </p>
            @endif
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
                <tr>
                    <td style="padding:10px 0;border-top:1px solid #f3f4f6;color:#6b7280;">{{ __('Registration') }}</td>
                    <td style="padding:10px 0;border-top:1px solid #f3f4f6;">{{ $event->registration_start->format('Y-m-d H:i') }} — {{ $event->registration_end->format('Y-m-d H:i') }}</td>
                </tr>
            </table>
        </td>
    </tr>
    @if(count($answersSummary) > 0)
        <tr>
            <td style="padding:0 32px 24px;">
                <p style="margin:0 0 14px;font-size:13px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">{{ __('Your answers') }}</p>
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-size:15px;line-height:1.55;">
                    @foreach($answersSummary as $label => $display)
                        <tr>
                            <td style="padding:10px 0;border-top:1px solid #f3f4f6;vertical-align:top;color:#6b7280;width:39%;">{{ $label }}</td>
                            <td style="padding:10px 0;border-top:1px solid #f3f4f6;vertical-align:top;color:#374151;">{{ $display }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    @endif
    @if($showAttendanceQr)
        <tr>
            <td style="padding:0 32px 28px;text-align:center;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background-color:#f9fafb;border-radius:14px;border:1px solid #f3f4f6;">
                    <tr>
                        <td style="padding:24px 24px;">
                            <p style="margin:0 0 8px;font-size:13px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">{{ __('Attendance QR code') }}</p>
                            <p style="margin:0 0 18px;font-size:14px;line-height:1.55;color:#6b7280;">{{ __('This QR is tied to your submission. Present it for check-in at the event.') }}</p>
                            <img src="data:image/png;base64,{{ $qrBase64 }}" alt="{{ __('Attendance QR code') }}" width="240" height="240" style="display:block;margin:0 auto;border:1px solid #e5e7eb;border-radius:12px;background-color:#ffffff;">
                            <p style="margin:16px 0 0;font-size:12px;color:#9ca3af;">{{ __('Submission ID') }}: {{ $submission->id }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    @else
        <tr>
            <td style="padding:0 32px 12px;">
                <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.55;"><strong>{{ __('Submission ID') }}:</strong> {{ $submission->id }}</p>
            </td>
        </tr>
    @endif
    @include('mail.partials.registration-details-button')
    @include('mail.partials.card-footer-by-app')
@endsection
