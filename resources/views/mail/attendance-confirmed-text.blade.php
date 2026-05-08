{{ __('Attendance confirmed') }} — {{ $event->title }}

{{ __('Hello') }} {{ $user->name }},

{{ __('Your attendance has been recorded.') }}

{{ __('Recorded at') }}: {{ $attendance->scanned_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
{{ __('Location') }}: {{ $event->location }}
{{ __('Event dates') }}: {{ $event->start_date->format('Y-m-d') }} — {{ $event->end_date->format('Y-m-d') }}

{{ __('Form') }}: {{ $form->title }}

@if($submission->registration_code)
{{ __('Registration code') }}: {{ $submission->registration_code }}
@endif

{{ __('If you did not attend this event, contact the organizer.') }}
