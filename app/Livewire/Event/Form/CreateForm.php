<?php

namespace App\Livewire\Event\Form;

use App\Enums\EventFormVisibility;
use App\Models\Form;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

class CreateForm extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public array $newData = [
        'title' => '',
        'description' => '',
        'closed_date' => '',
        'closed_time' => '',
        'visibleFor' => '',
        'event_id' => ''
    ];

    public function createSchema(Schema $schema): Schema
    {
        $today = now('+7');

        return $schema->components([
            TextInput::make('title')
                ->label(ucfirst(__('events.forms.title')))
                ->required()
                ->columnSpanFull(),
            Textarea::make('description')
                ->label(ucfirst(__('events.forms.description')))
                ->autosize()
                ->required()
                ->columnSpanFull(),
            DatePicker::make('closed_date')
                ->label(ucfirst(__('events.forms.closed_date')))
                ->prefixIcon(Heroicon::CalendarDays)
                ->minDate($today->addDay()->format('Y-m-d'))
                ->native(false)
                ->live(onBlur: true, debounce: 1500)
                ->closeOnDateSelection()
                ->required(),
            TimePicker::make('closed_time')
                ->label(ucfirst(__('events.forms.closed_time')))
                ->prefixIcon(Heroicon::Clock)
                ->native(false)
                ->minutesStep(15)
                ->secondsStep(60)
                ->live(onBlur: true, debounce: 1500)
                ->required(),
            Select::make('visible_for')
                ->label(ucfirst(__('events.forms.visible_for')))
                ->helperText(__('Can fill more than one'))
                ->options(EventFormVisibility::class)
                ->multiple()
                ->required(),
            Hidden::make('event_id')
        ])
            ->columns([
                'default' => 1,
                'xl' => 2
            ])
            ->statePath("newData");
    }

    public function save()
    {
        $validatedData = $this->createSchema->getState();

        $validatedData['closed_at'] = "{$validatedData['closed_date']} {$validatedData['closed_time']}";

        unset($validatedData['closed_date']);
        unset($validatedData['closed_time']);

        if (Form::create($validatedData)) {
            Notification::make()
                ->title(__('Notification'))
                ->body(__('messages.form.create.success'))
                ->success()
                ->send();

            return to_route("dashboard.events.show", ['event' => $validatedData['event_id']]);
        }

        Notification::make()
            ->danger()
            ->title('Alert')
            ->body(__('messages.form.create.failed'))
            ->send();

        return null;
    }

    public function mount(string $event_id): void
    {
        $this->createSchema->fill([
            'title' => '',
            'description' => '',
            'closed_date' => '',
            'closed_time' => '',
            'visible_for' => '',
            'event_id' => $event_id
        ]);
    }

    public function render()
    {
        $eventId = request()->route('event');

        return view('livewire.event.form.create-form', [
            'eventId' => $eventId
        ]);
    }
}
