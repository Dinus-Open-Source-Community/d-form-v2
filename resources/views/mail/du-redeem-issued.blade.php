@extends('mail.layouts.base')

@section('mail_title', __('DU redeem code issued'))

@section('mail_card')
    @include('mail.partials.card-header', [
        'eyebrow' => __('D-Kelas'),
        'accent' => '#2563eb',
        'headline' => $event->title,
        'formTitle' => $form->title,
    ])
    <tr>
        <td style="padding:24px 32px 0;">
            <p style="margin:0 0 18px;font-size:16px;line-height:1.65;color:#374151;">
                <strong style="color:#111827;">{{ __('Hello') }} {{ $recipientName }},</strong>
            </p>
            <p style="margin:0;font-size:16px;line-height:1.65;color:#374151;">
                {{ __('Your D-Kelas redeem code has been issued. Use the code below to redeem your class access.') }}
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding:24px 32px 12px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background-color:#eff6ff;border-radius:14px;border:1px solid #bfdbfe;text-align:center;">
                <tr>
                    <td style="padding:24px;">
                        <p style="margin:0 0 8px;font-size:13px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#1d4ed8;">{{ __('DU redeem code') }}</p>
                        <p style="margin:0;padding:14px 18px;font-size:22px;font-weight:700;letter-spacing:0.06em;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;color:#111827;background-color:#ffffff;border-radius:10px;border:1px dashed #93c5fd;">{{ $redeemCode }}</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="padding:12px 32px 28px;text-align:center;">
            <p style="margin:0 0 18px;font-size:13px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">{{ __('Attendance QR code') }}</p>
            <p style="margin:0 0 18px;font-size:14px;line-height:1.55;color:#6b7280;">{{ __('Show this QR code at event check-in.') }}</p>
            <img src="cid:qr-code.png" alt="{{ __('Attendance QR code') }}" width="240" height="240" style="display:block;margin:0 auto;border:1px solid #e5e7eb;border-radius:12px;background-color:#ffffff;">
        </td>
    </tr>
    @include('mail.partials.registration-details-button')
@endsection
