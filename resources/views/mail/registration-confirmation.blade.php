<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Registration confirmed') }}</title>
</head>
<body style="margin:0;padding:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;line-height:1.5;color:#111827;background:#f3f4f6;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden;">
                    <tr>
                        <td style="padding:24px 28px 8px;">
                            <p style="margin:0;font-size:13px;font-weight:600;color:#4f46e5;text-transform:uppercase;letter-spacing:0.06em;">{{ __('Registration') }}</p>
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
                                {{ __('Your registration has been received. Here are the event details and your answers.') }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:12px 28px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:14px;border-collapse:collapse;">
                                <tr>
                                    <td colspan="2" style="padding:8px 0 4px;font-weight:600;border-bottom:1px solid #e5e7eb;">{{ __('Event details') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;color:#6b7280;width:38%;">{{ __('Location') }}</td>
                                    <td style="padding:8px 0;">{{ $event->location }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;color:#6b7280;">{{ __('Event dates') }}</td>
                                    <td style="padding:8px 0;">{{ $event->start_date->format('Y-m-d') }} — {{ $event->end_date->format('Y-m-d') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;color:#6b7280;">{{ __('Registration') }}</td>
                                    <td style="padding:8px 0;">{{ $event->registration_start->format('Y-m-d H:i') }} — {{ $event->registration_end->format('Y-m-d H:i') }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @if(count($answersSummary) > 0)
                    <tr>
                        <td style="padding:4px 28px 12px;">
                            <p style="margin:0 0 8px;font-size:14px;font-weight:600;">{{ __('Your answers') }}</p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:13px;border-collapse:collapse;">
                                @foreach($answersSummary as $label => $display)
                                <tr>
                                    <td style="padding:6px 0;vertical-align:top;color:#6b7280;width:38%;">{{ $label }}</td>
                                    <td style="padding:6px 0;vertical-align:top;">{{ $display }}</td>
                                </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <td style="padding:8px 28px 4px;text-align:center;">
                            <p style="margin:0 0 8px;font-size:14px;font-weight:600;">{{ __('Attendance QR code') }}</p>
                            <p style="margin:0 0 12px;font-size:12px;color:#6b7280;">{{ __('Show this code at check-in for') }} {{ $event->title }}.</p>
                            <img src="data:image/png;base64,{{ $qrBase64 }}" alt="QR" width="240" height="240" style="display:inline-block;border:1px solid #e5e7eb;border-radius:8px;">
                            <p style="margin:12px 0 0;font-size:11px;color:#9ca3af;">{{ __('Submission ID') }}: {{ $submission->id }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 28px 24px;">
                            <p style="margin:0;font-size:12px;color:#9ca3af;">{{ __('This message was sent because you registered for an event on :app.', ['app' => config('app.name')]) }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
