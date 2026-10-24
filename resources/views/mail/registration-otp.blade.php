@extends('mail.layouts.base')

@section('mail_title', 'Kode verifikasi DForm')

@section('mail_preheader')
    Kode verifikasi DForm kamu berlaku {{ $ttlMinutes }} menit.
@endsection

@section('mail_card')
    @include('mail.partials.card-header', [
        'eyebrow' => 'Verifikasi email',
        'accent' => '#178ADF',
        'headline' => 'Kode verifikasi DForm',
    ])
    <tr>
        <td style="padding:24px 32px 0;">
            <p style="margin:0 0 18px;font-size:16px;line-height:1.65;color:#374151;">
                <strong style="color:#111827;">Halo {{ $recipientName }},</strong>
            </p>
            <p style="margin:0 0 18px;font-size:16px;line-height:1.65;color:#374151;">
                Masukkan kode berikut untuk menyelesaikan pendaftaran akun DForm kamu:
            </p>
            <p style="margin:0;padding:18px 20px;text-align:center;font-size:32px;font-weight:700;letter-spacing:0.3em;color:#111827;background-color:#f3f4f6;border-radius:12px;border:1px solid #e5e7eb;">
                {{ $code }}
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding:20px 32px 28px;">
            <p style="margin:0 0 10px;font-size:14px;line-height:1.6;color:#6b7280;">
                Kode ini berlaku {{ $ttlMinutes }} menit sejak email dikirim dan hanya bisa dipakai sekali.
            </p>
            <p style="margin:0;font-size:14px;line-height:1.6;color:#6b7280;">
                Kalau kamu tidak merasa mendaftar di DForm, abaikan email ini — tanpa kode tersebut akun tidak akan dibuat.
            </p>
        </td>
    </tr>
@endsection
