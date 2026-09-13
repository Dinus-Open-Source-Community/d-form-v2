<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>{{ $subjectLine ?? 'Konfirmasi Pendaftaran OpRec' }}</title>
    </head>
    <body style="font-family: system-ui, sans-serif; line-height: 1.6; color: #111827;">
        {!! $bodyHtml !!}

        @if(!empty($qrPngBinary))
            <div style="margin-top: 24px; padding: 20px; border: 1px solid #e5e7eb; border-radius: 12px; background: #f9fafb;">
                <p style="margin: 0 0 12px; font-size: 15px; font-weight: 600; color: #111827;">
                    QR Code Absensi Interview
                </p>
                <p style="margin: 0 0 16px; font-size: 14px; color: #4b5563;">
                    Tunjukkan QR code ini kepada panitia saat tiba di lokasi interview. Absensi hanya dapat diproses oleh panitia melalui scanner.
                </p>
                <img
                    src="cid:qr-code-interview.png"
                    alt="QR Code Absensi Interview"
                    width="240"
                    height="240"
                    style="display: block; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 8px; background-color: #ffffff;"
                />
            </div>
        @endif
    </body>
</html>
