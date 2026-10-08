@extends('mail.layouts.base')

@section('mail_title'){{ $subjectLine ?? 'Konfirmasi Pendaftaran OpRec' }}@endsection

@section('mail_card')
    <tr>
        <td style="padding:0 40px 10px;color:#333;">
            <h3 style="margin:0;font-weight:bold;">{{ $headline ?? $subjectLine ?? 'Konfirmasi Pendaftaran OpRec' }}</h3>
        </td>
    </tr>
    <tr>
        <td style="padding:0 40px 30px;color:#333;">
            {!! $bodyHtml !!}
        </td>
    </tr>
    @if(!empty($qrPngBinary))
        <tr>
            <td style="padding:0 40px 30px;text-align:center;">
                <table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;background-color:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;margin:0;">
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
@endsection
