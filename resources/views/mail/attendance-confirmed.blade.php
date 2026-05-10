@extends('mail.layouts.base')

@section('mail_title', __('Attendance confirmed'))

@section('mail_card')
    @include('mail.partials.card-header', [
        'eyebrow' => __('Checked in'),
        'accent' => '#059669',
        'headline' => $event->title,
        'formTitle' => $form->title,
    ])
    <tr>
        <td style="padding:24px 32px 0;">
            <p style="margin:0 0 18px;font-size:16px;line-height:1.65;color:#374151;">
                <strong style="color:#111827;">{{ __('Hello') }} {{ $user->name }},</strong>
            </p>
            <p style="margin:0;padding:16px 18px;font-size:16px;line-height:1.65;color:#14532d;background-color:#f0fdf4;border-radius:12px;border:1px solid #bbf7d0;">
                {{ __('Your attendance has been recorded.') }}
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding:24px 32px;">
            <p style="margin:0 0 14px;font-size:13px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">{{ __('Details') }}</p>
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-size:15px;line-height:1.55;color:#374151;">
                <tr>
                    <td style="padding:10px 0;border-top:1px solid #f3f4f6;color:#6b7280;width:42%;">{{ __('Recorded at') }}</td>
                    <td style="padding:10px 0;border-top:1px solid #f3f4f6;">{{ $attendance->scanned_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 0;border-top:1px solid #f3f4f6;color:#6b7280;">{{ __('Location') }}</td>
                    <td style="padding:10px 0;border-top:1px solid #f3f4f6;">{{ $event->location }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 0;border-top:1px solid #f3f4f6;color:#6b7280;">{{ __('Event dates') }}</td>
                    <td style="padding:10px 0;border-top:1px solid #f3f4f6;">{{ $event->start_date->format('Y-m-d') }} — {{ $event->end_date->format('Y-m-d') }}</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="padding:0 32px 28px;">
            <p style="margin:0 0 10px;font-size:14px;line-height:1.55;color:#6b7280;">{{ __('If you did not attend this event, contact the organizer.') }}</p>
        </td>
    </tr>
    @include('mail.partials.card-footer-by-app')
@endsection
