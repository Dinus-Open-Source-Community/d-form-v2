{{ __('Team invitation') }} — {{ $event->title }}

{{ __('Form') }}: {{ $form->title }}

{{ __('Hello :name,', ['name' => $member->name ?? __('there')]) }}

{{ __(':leader has registered a team for :event and added you as a member. Please review and confirm your participation.', [
    'leader' => $leader->name ?? __('Your team leader'),
    'event' => $event->title,
]) }}

────────────────────────
{{ __('Event details') }}

{{ __('Location') }}: {{ $event->location }}
{{ __('Event dates') }}: {{ $event->start_date->format('Y-m-d') }} — {{ $event->end_date->format('Y-m-d') }}

────────────────────────
{{ __('Review and confirm') }}:
{{ $confirmUrl }}

────────────────────────
{{ __('This message was sent by :app.', ['app' => config('app.name')]) }}
