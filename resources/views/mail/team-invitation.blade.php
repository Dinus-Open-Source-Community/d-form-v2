@php
    /** @var \App\Models\FormAnswer $memberSubmission */
    /** @var \App\Models\Event $event */
    /** @var \App\Models\Form $form */
    /** @var \App\Models\User|null $member */
    /** @var \App\Models\User|null $leader */
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #1a1a1a;">
    <p>{{ __('Hello :name,', ['name' => $member->name ?? __('there')]) }}</p>
    <p>
        {{ __(':leader has registered a team for :event and added you as a member. Please review the registration details and confirm your participation.', [
            'leader' => $leader->name ?? __('Your team leader'),
            'event' => $event->title,
        ]) }}
    </p>
    <p>{{ __('Form: :title', ['title' => $form->title]) }}</p>
    <p style="margin: 24px 0;">
        <a href="{{ $confirmUrl }}" style="display: inline-block; padding: 12px 20px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 8px;">
            {{ __('Review and confirm') }}
        </a>
    </p>
    <p style="font-size: 14px; color: #666;">{{ __('If the button does not work, copy this link into your browser:') }}<br>
        <span style="word-break: break-all;">{{ $confirmUrl }}</span>
    </p>
</body>
</html>
