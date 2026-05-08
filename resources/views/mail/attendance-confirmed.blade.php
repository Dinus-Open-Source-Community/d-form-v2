<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Attendance confirmed') }}</title>
</head>
<body style="margin:0;padding:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;line-height:1.5;color:#111827;background:#f3f4f6;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden;">
                    <tr>
                        <td style="padding:24px 28px 8px;">
                            <p style="margin:0;font-size:13px;font-weight:600;color:#2563eb;text-transform:uppercase;letter-spacing:0.06em;">{{ __('Checked in') }}</p>
                            <h1 style="margin:8px 0 0;font-size:22px;font-weight:700;color:#111827;">{{ $event->title }}</h1>
                            <p style="margin:12px 0 0;font-size:14px;color:#4b5563;">
                                {{ __('Form') }}: <strong>{{ $form->title }}</strong>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 28px 4px;">
                            <p style="margin:0;font-size:14px;color:#374151;">
                                <strong>{{ __('Hello') }} {{ $user->name }},</strong><br>
                                {{ __('Your attendance has been recorded.') }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:12px 28px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:14px;border-collapse:collapse;">
                                <tr>
                                    <td colspan="2" style="padding:8px 0 4px;font-weight:600;border-bottom:1px solid #e5e7eb;">{{ __('Details') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;color:#6b7280;width:42%;">{{ __('Recorded at') }}</td>
                                    <td style="padding:8px 0;">{{ $attendance->scanned_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;color:#6b7280;">{{ __('Location') }}</td>
                                    <td style="padding:8px 0;">{{ $event->location }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;color:#6b7280;">{{ __('Event dates') }}</td>
                                    <td style="padding:8px 0;">{{ $event->start_date->format('Y-m-d') }} — {{ $event->end_date->format('Y-m-d') }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 28px 24px;">
                            <p style="margin:0;font-size:12px;color:#9ca3af;">{{ __('If you did not attend this event, contact the organizer.') }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
