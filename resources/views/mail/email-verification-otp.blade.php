@extends('mail.layouts.base')

@php($pinRecovery = $pinRecovery ?? false)

@section('mail_title', $pinRecovery ? 'Kode pemulihan PIN DForm' : 'Verifikasi email DForm')

@section('mail_preheader')
    @if ($pinRecovery)
        Kode pemulihan PIN DForm kamu berlaku {{ $ttlMinutes }} menit.
    @else
        Kode verifikasi email akun DForm kamu berlaku {{ $ttlMinutes }} menit.
    @endif
@endsection

@section('mail_card')
    @include('mail.partials.card-header', [
        'eyebrow' => $pinRecovery ? 'Pemulihan PIN' : 'Verifikasi email',
        'accent' => '#178ADF',
        'headline' => $pinRecovery ? 'Reset PIN DForm' : 'Verifikasi email DForm',
    ])
    <tr>
        <td style="padding:24px 32px 0;">
            <p style="margin:0 0 18px;font-size:16px;line-height:1.65;color:#374151;">
                <strong style="color:#111827;">Halo {{ $recipientName }},</strong>
            </p>
            <p style="margin:0 0 18px;font-size:16px;line-height:1.65;color:#374151;">
                @if ($pinRecovery)
                    Masukkan kode berikut untuk mengatur ulang PIN login DForm kamu:
                @else
                    Masukkan kode berikut untuk memverifikasi email akun DForm kamu:
                @endif
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
                @if ($pinRecovery)
                    Kalau kamu tidak merasa meminta pemulihan PIN, abaikan email ini. PIN dan akses akun kamu tidak berubah.
                @else
                    Kalau kamu tidak merasa membuat akun atau meminta verifikasi ini, abaikan email ini. Email akun akan tetap seperti sebelumnya.
                @endif
            </p>
        </td>
    </tr>
@endsection
