<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Registration update') }}</title>
</head>
<body style="margin:0;padding:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;line-height:1.5;color:#111827;background:#f3f4f6;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden;">
                    <tr>
                        <td style="padding:24px 28px 8px;">
                            <p style="margin:0;font-size:13px;font-weight:600;color:#b45309;text-transform:uppercase;letter-spacing:0.06em;">{{ __('Update') }}</p>
                            <h1 style="margin:8px 0 0;font-size:22px;font-weight:700;color:#111827;">{{ $event->title }}</h1>
                            <p style="margin:12px 0 0;font-size:14px;color:#4b5563;">
                                {{ __('Form') }}: <strong>{{ $form->title }}</strong>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 28px 24px;">
                            <p style="margin:0;font-size:14px;color:#374151;">
                                <strong>{{ __('Hello') }} {{ $user->name }},</strong><br><br>
                                {{ __('Thank you for your interest. Unfortunately, we are unable to accept your registration for this event at this time.') }}
                            </p>
                        </td>
                    </tr>
                    @include('mail.partials.registration-details-button')
                    <tr>
                        <td style="padding:0 28px 24px;">
                            <p style="margin:0;font-size:12px;color:#9ca3af;">{{ __('This message was sent because you registered for an event on :app.', ['app' => config('app.name')]) }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
