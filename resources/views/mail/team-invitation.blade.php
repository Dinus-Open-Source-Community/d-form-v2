@php
    /** @var \App\Models\FormAnswer $memberSubmission */
    /** @var \App\Models\Event $event */
    /** @var \App\Models\Form $form */
    /** @var \App\Models\User|null $member */
    /** @var \App\Models\User|null $leader */
@endphp
@extends('mail.layouts.base')

@section('mail_title', __('Team invitation'))

@section('mail_card')
    @include('mail.partials.card-header', [
        'eyebrow' => __('Invitation'),
        'accent' => '#4f46e5',
        'headline' => $event->title,
        'formTitle' => $form->title,
    ])
    <tr>
        <td style="padding:24px 32px 0;">
            <p style="margin:0 0 18px;font-size:16px;line-height:1.65;color:#374151;">
                <strong style="color:#111827;">{{ __('Hello :name,', ['name' => $member->name ?? __('there')]) }}</strong>
            </p>
            <p style="margin:0 0 18px;font-size:16px;line-height:1.65;color:#374151;">
                {{ __(':leader has registered a team for :event and added you as a member. Please review the registration details and confirm your participation.', [
                    'leader' => $leader->name ?? __('Your team leader'),
                    'event' => $event->title,
                ]) }}
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding:0 32px 24px;">
            <p style="margin:0 0 14px;font-size:13px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">{{ __('Event details') }}</p>
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-size:15px;line-height:1.55;color:#374151;">
                <tr>
                    <td style="padding:10px 0;border-top:1px solid #f3f4f6;color:#6b7280;width:39%;">{{ __('Location') }}</td>
                    <td style="padding:10px 0;border-top:1px solid #f3f4f6;">{{ $event->location }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 0;border-top:1px solid #f3f4f6;color:#6b7280;">{{ __('Event dates') }}</td>
                    <td style="padding:10px 0;border-top:1px solid #f3f4f6;">{{ $event->start_date->format('Y-m-d') }} — {{ $event->end_date->format('Y-m-d') }}</td>
                </tr>
            </table>
        </td>
    </tr>
    @include('mail.partials.primary-action', ['url' => $confirmUrl, 'label' => __('Review and confirm')])
    @include('mail.partials.card-footer-by-app')
@endsection
