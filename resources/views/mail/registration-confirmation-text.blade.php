{{ __('Registration received') }} — {{ $event->title }}

{{ __('Form') }}: {{ $form->title }}

{{ __('Hello') }} {{ $user->name }},

{{ __('Thank you — we have received your registration. Our team will review it and you will receive another email once a decision has been made.') }}

@if(!$showAttendanceQr)
{{ __('This email does not include an attendance QR code. Once an administrator has approved your registration, you will receive another email with your QR code for event check-in.') }}
@endif

────────────────────────
{{ __('Event details') }}

{{ __('Location') }}: {{ $event->location }}
{{ __('Event dates') }}: {{ $event->start_date->format('Y-m-d') }} — {{ $event->end_date->format('Y-m-d') }}
{{ __('Registration') }}: {{ $event->registration_start->format('Y-m-d H:i') }} — {{ $event->registration_end->format('Y-m-d H:i') }}

@if(count($answersSummary) > 0)
────────────────────────
{{ __('Your answers') }}
@foreach($answersSummary as $label => $display)
• {{ $label }}: {{ $display }}
@endforeach
@endif

@if($showAttendanceQr)
────────────────────────
{{ __('Attendance QR code') }} — {{ __('The attendance QR image is included in the HTML version of this email.') }}
@endif

{{ __('Submission ID') }}: {{ $submission->id }}

────────────────────────
{{ __('View registration details') }}:
{{ $registrationDetailsUrl }}

────────────────────────
{{ __('This message was sent by :app.', ['app' => config('app.name')]) }}
