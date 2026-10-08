@extends('mail.layouts.base')

@section('mail_title')
    @if(!empty($forInvitationSelfDeclined))
        {{ __('Invitation update') }}
    @else
        {{ __('Registration update') }}
    @endif
@endsection

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
                <strong style="color:#111827;">{{ __('Hello') }} {{ $recipientName }},</strong>
            </p>
            @if(!empty($forInvitationSelfDeclined))
                <p style="margin:0;padding:16px 18px;font-size:16px;line-height:1.65;color:#92400e;background-color:#fffbeb;border-radius:12px;border:1px solid #fcd34d;">
                    {{ __('You have declined this registration invitation. You will not be listed as a participant for this team registration unless you are invited again.') }}
                </p>
            @elseif(!empty($forTeammateDeclinedLeaderNotice))
                <p style="margin:0;padding:16px 18px;font-size:16px;line-height:1.65;color:#7f1d1d;background-color:#fef2f2;border-radius:12px;border:1px solid #fecaca;">
                    {{ __(':name has declined the registration invitation for your team. They will not be included as a participant for this submission.', ['name' => $teammateDisplayName]) }}
                </p>
                @if(!empty($declineReason))
                    <p style="margin:18px 0 0;padding:16px 18px;font-size:15px;line-height:1.65;color:#1f2937;background-color:#f9fafb;border-radius:12px;border:1px solid #e5e7eb;">
                        <strong style="color:#111827;display:block;margin-bottom:8px;">{{ __('Message from your teammate') }}</strong>
                        {!! nl2br(e($declineReason)) !!}
                    </p>
                @endif
            @else
                <p style="margin:0;padding:16px 18px;font-size:16px;line-height:1.65;color:#7f1d1d;background-color:#fef2f2;border-radius:12px;border:1px solid #fecaca;">
                    {{ __('Thank you for your interest. Unfortunately, we are unable to accept your registration for this event at this time.') }}
                </p>
            @endif
        </td>
    </tr>
    @include('mail.partials.registration-details-button')
@endsection
