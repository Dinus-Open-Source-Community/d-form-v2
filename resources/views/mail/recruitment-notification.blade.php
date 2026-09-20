@extends('mail.layouts.base')

@section('mail_title'){{ $subjectLine ?? 'Konfirmasi Pendaftaran OpRec' }}@endsection

@section('mail_card')
    <tr>
        <td style="padding:28px 32px 22px;border-bottom:1px solid #f3f4f6;">
            <h1 style="margin:0;font-size:24px;font-weight:700;color:#111827;line-height:1.3;">{{ $headline ?? $subjectLine ?? 'Konfirmasi Pendaftaran OpRec' }}</h1>
        </td>
    </tr>
    <tr>
        <td style="padding:24px 24px 8px;">
            {!! $bodyHtml !!}
        </td>
    </tr>
    @if(!empty($qrPngBinary))
        <tr>
            <td style="padding:16px 24px 24px;text-align:center;">
                <table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0" bgcolor="#f9fafb" style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;background-color:#f9fafb;border-radius:14px;border:1px solid #f3f4f6;">
                    <tr>
                        <td style="padding:20px 20px;">
                            <p style="margin:0 0 8px;font-size:13px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">
                                QR Code Absensi Interview
                            </p>
                            <p style="margin:0 0 18px;font-size:14px;line-height:1.55;color:#6b7280;">
                                Tunjukkan QR code ini kepada panitia saat tiba di lokasi interview. Absensi hanya dapat diproses oleh panitia melalui scanner.
                            </p>
                            <img
                                src="cid:qr-code-interview.png"
                                alt="QR Code Absensi Interview"
                                width="240"
                                height="240"
                                style="display:block;margin:0 auto;max-width:100%;height:auto;-ms-interpolation-mode:bicubic;border:1px solid #e5e7eb;border-radius:12px;background-color:#ffffff;"
                            />
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    @endif
    @include('mail.partials.card-footer-by-app')
@endsection
