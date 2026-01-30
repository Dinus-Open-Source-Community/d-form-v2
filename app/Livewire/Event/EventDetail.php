<?php

namespace App\Livewire\Event;

use App\Models\Event;
use Carbon\Carbon;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Livewire\Component;

class EventDetail extends Component implements HasSchemas, HasInfolists
{
    use InteractsWithSchemas;
    use InteractsWithInfolists;

    public Event $event;

    public function eventDetailInfolist(Schema $schema): Schema
    {
        return $schema
            ->record($this->event)
            ->components([
                ImageEntry::make('banner')
                    ->hiddenLabel()
                    ->disk('public')
                    ->visibility('public')
                    ->imageWidth("100%")
                    ->imageHeight("100%")
                    ->extraImgAttributes([
                        'class' => 'aspect-video object-cover object-center'
                    ])
                    ->extraAttributes([
                        'class' => 'aspect-video overflow-hidden'
                    ]),
                TextEntry::make('title')
                    ->hiddenLabel()
                    ->size(TextSize::Large)
                    ->weight(FontWeight::Bold),
                TextEntry::make('description')
                    ->hiddenLabel(),
                Grid::make([
                    'default' => 2,
                ])
                    ->components([
                        TextEntry::make('price')
                            ->label(ucfirst(__('events.price')))
                            ->prefix('Rp. '),
                        TextEntry::make('quota')
                            ->label(ucfirst(__('events.quota'))),
                        TextEntry::make('location')
                            ->label(ucfirst(__('events.location'))),
                    ]),
                Grid::make([
                    'default' => 1,
                    'md' => 2
                ])
                    ->components([
                        TextEntry::make('registration_start')
                            ->label(ucfirst(__('events.registration_start')))
                            ->formatStateUsing(fn ($state) => Carbon::parse($state)->isoFormat('dddd, DD MMMM Y')),
                        TextEntry::make('registration_end')
                            ->label(ucfirst(__('events.registration_end')))
                            ->formatStateUsing(fn ($state) => Carbon::parse($state)->isoFormat('dddd, DD MMMM Y')),
                        TextEntry::make('start_date')
                            ->label(ucfirst(__('events.start_date')))
                            ->formatStateUsing(fn ($state) => Carbon::parse($state)->isoFormat('dddd, DD MMMM Y')),
                        TextEntry::make('end_date')
                            ->label(ucfirst(__('events.end_date')))
                            ->formatStateUsing(fn ($state) => Carbon::parse($state)->isoFormat('dddd, DD MMMM Y')),
                    ]),
                Flex::make([
                    TextEntry::make('session')
                        ->hiddenLabel()
                        ->badge()
                        ->grow(false),
                    TextEntry::make('category')
                        ->hiddenLabel()
                        ->badge()
                        ->grow(false),
                    TextEntry::make('status')
                        ->hiddenLabel()
                        ->badge()
                        ->grow(false),
                ])
            ]);
    }

    public function mount()
    {
    }

    public function render()
    {
        return view('livewire.event.event-detail');
    }
}
