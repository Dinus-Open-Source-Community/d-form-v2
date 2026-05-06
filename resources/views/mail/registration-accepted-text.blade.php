{{ __('Registration accepted') }}: {{ $event->title }}

{{ __('Form') }}: {{ $form->title }}

{{ __('Hello') }} {{ $user->name }},

{{ __('Great news — your registration has been accepted. Use the QR code (HTML email) or manual code below for check-in.') }}

---
{{ __('Event details') }}
{{ __('Location') }}: {{ $event->location }}
{{ __('Event dates') }}: {{ $event->start_date->format('Y-m-d') }} — {{ $event->end_date->format('Y-m-d') }}

{{ __('Manual registration code') }}: {{ $registrationCode }}

{{ __('Submission ID') }}: {{ $submission->id }}
