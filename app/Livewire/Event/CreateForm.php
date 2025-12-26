<?php

namespace App\Livewire\Event;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use App\Enums\EventSession;
use App\Models\Event;
use Filament\Notifications\Notification;
use Filament\Support\RawJs;
use Livewire\Features\SupportRedirects\Redirector;
use Livewire\WithFileUploads;
use NumberFormatter;

class CreateForm extends Component implements HasSchemas
{
    use InteractsWithSchemas;
    use WithFileUploads;

    public array $newEventData = [];

    public function createSchema(Schema $schema): Schema
    {
        $today = now()->startOfDay();
        $tomorrow = now()->startOfDay()->addDay();

        return $schema->components([
            TextInput::make('title')
                ->label(ucfirst(__('events.title')))
                ->maxLength(100)
                ->required(),
            TextInput::make('location')
                ->label(ucfirst(__('events.location')))
                ->maxLength(100)
                ->required(),
            Textarea::make('description')
                ->label(ucfirst(__('events.description')))
                ->autosize()
                ->required()
                ->columnSpanFull(),
            DatePicker::make('start_date')
                ->label(ucfirst(__('events.start_date')))
                ->native(false)
                ->minDate($today)
                ->displayFormat('l, d F Y')
                ->placeholder($today->translatedFormat('l, d F Y'))
                ->required(),
            DatePicker::make('end_date')
                ->label(ucfirst(__('events.end_date')))
                ->native(false)
                ->minDate($tomorrow)
                ->displayFormat('l, d F Y')
                ->placeholder($tomorrow->translatedFormat('l, d F Y'))
                ->required(),
            DatePicker::make('registration_start')
                ->label(ucfirst(__('events.registration_start')))
                ->native(false)
                ->minDate($today)
                ->displayFormat('l, d F Y')
                ->placeholder($today->translatedFormat('l, d F Y'))
                ->required(),
            DatePicker::make('registration_end')
                ->label(ucfirst(__('events.registration_end')))
                ->native(false)
                ->minDate($tomorrow)
                ->displayFormat('l, d F Y')
                ->placeholder($tomorrow->translatedFormat('l, d F Y'))
                ->required(),
            TextInput::make('quota')
                ->label(ucfirst(__('events.quota')))
                ->numeric()
                ->step(5)
                ->mask('9999999')
                ->required(),
            TextInput::make('price')
                ->label(ucfirst(__('events.price')))
                ->prefix("Rp. ")
                ->mask(RawJs::make('$money($input)'))
                ->required(),
            Select::make('session')
                ->label(ucfirst(__('events.session')))
                ->options(EventSession::class)
                ->required()
                ->native(false),
            Select::make('category')
                ->label(ucfirst(__('events.category')))
                ->options(EventCategory::class)
                ->required()
                ->native(false),
            FileUpload::make('banner')
                ->label(ucfirst(__('events.banner')))
                ->columnSpanFull()
                ->disk('public')
                ->directory('events/banners')
                ->visibility('public')
                ->image()
                ->helperText('Aspect ratio 16:9')
                ->maxSize(10 * 1024)
                ->required(),
        ])
            ->columns([
                'default' => 1,
                'md' => 2
            ])
            ->statePath('newEventData');
    }

    public function save(bool $isPublished = false): ?Redirector
    {
        $validatedData = $this->createSchema->getState();

        $validatedData['status'] = $isPublished ? EventStatus::Published : EventStatus::Draft;

        $validatedData['price'] = (new NumberFormatter(env('APP_LOCALE'), NumberFormatter::DECIMAL))->parse($validatedData['price']);

        if (Event::create($validatedData)) {
            Notification::make()
                ->success()
                ->title('Notification')
                ->body('Berhasil membuat event baru')
                ->send();

            $this->createSchema->fill([
                'banner' => ''
            ]);

            return to_route('dashboard.events.index');
        }

        $this->createSchema->fill([
            'banner' => ''
        ]);

        Notification::make()
            ->danger()
            ->title('Alert')
            ->body('Gagal membuat event baru')
            ->send();

        return null;
    }

    public function mount()
    {
        $this->createSchema->fill([
            'title' => '',
            'description' => '',
            'start_date' => '',
            'end_date' => '',
            'registration_start' => '',
            'registration_end' => '',
            'location' => '',
            'quota' => '',
            'banner' => '',
            'price' => '',
            'session' => ''
        ]);
    }

    public function render(): View
    {
        return view('livewire.event.create-form');
    }
}
