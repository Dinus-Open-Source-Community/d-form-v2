{{ __('Registration confirmed') }}: {{ $event->title }}

{{ __('Form') }}: {{ $form->title }}

{{ __('Hello') }} {{ $user->name }},

{{ __('Your registration has been received.') }}

---
{{ __('Event details') }}
{{ __('Location') }}: {{ $event->location }}
{{ __('Event dates') }}: {{ $event->start_date->format('Y-m-d') }} — {{ $event->end_date->format('Y-m-d') }}

@if(count($answersSummary) > 0)
{{ __('Your answers') }}
@foreach($answersSummary as $label => $display)
- {{ $label }}: {{ $display }}
@endforeach
@endif

{{ __('Attendance QR code') }}: {{ __('See HTML version of this email for the image.') }}
{{ __('Submission ID') }}: {{ $submission->id }}
