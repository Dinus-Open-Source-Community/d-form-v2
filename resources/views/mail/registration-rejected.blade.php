@extends('mail.layouts.base')

@section('mail_title', __('Registration update'))

@section('mail_card')
    @include('mail.partials.card-header', [
        'eyebrow' => __('Decision'),
        'accent' => '#b91c1c',
        'headline' => $event->title,
        'formTitle' => $form->title,
    ])
    <tr>
        <td style="padding:24px 32px;">
            <p style="margin:0 0 18px;font-size:16px;line-height:1.65;color:#374151;">
                <strong style="color:#111827;">{{ __('Hello') }} {{ $user->name }},</strong>
            </p>
            <p style="margin:0;padding:16px 18px;font-size:16px;line-height:1.65;color:#7f1d1d;background-color:#fef2f2;border-radius:12px;border:1px solid #fecaca;">
                {{ __('Thank you for your interest. Unfortunately, we are unable to accept your registration for this event at this time.') }}
            </p>
        </td>
    </tr>
    @include('mail.partials.registration-details-button')
    @include('mail.partials.card-footer-by-app')
@endsection
