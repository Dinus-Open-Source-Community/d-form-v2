{{ __('Registration received') }}: {{ $event->title }}

{{ __('Form') }}: {{ $form->title }}

{{ __('Hello') }} {{ $user->name }},

{{ __('Thank you — we have received your registration. Our team will review it and you will receive another email once a decision has been made.') }}

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
