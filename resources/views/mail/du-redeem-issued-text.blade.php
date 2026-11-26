{{ __('DU redeem code issued') }} — {{ $event->title }}

{{ __('Form') }}: {{ $form->title }}

{{ __('Hello') }} {{ $recipientName }},

{{ __('Your D-Kelas redeem code has been issued. Use the code below to redeem your class access.') }}

────────────────────────
{{ __('DU redeem code') }}: {{ $redeemCode }}

{{ __('The HTML email includes your attendance QR code. Show it at event check-in.') }}

@if(!empty($showRegistrationAction))
{{ $registrationActionLabel }}:
{{ $registrationActionUrl }}
@endif

────────────────────────
{{ __('This message was sent by :app.', ['app' => config('app.name')]) }}
