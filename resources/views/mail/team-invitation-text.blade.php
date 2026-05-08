{{ __('Hello :name,', ['name' => $member->name ?? __('there')]) }}

{{ __(':leader has registered a team for :event and added you as a member. Please review and confirm your participation.', [
    'leader' => $leader->name ?? __('Your team leader'),
    'event' => $event->title,
]) }}

{{ __('Form: :title', ['title' => $form->title]) }}

{{ __('Open this link to review and confirm:') }}
{{ $confirmUrl }}
