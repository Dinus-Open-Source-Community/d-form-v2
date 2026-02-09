<?php

namespace App\Livewire\Event;

use App\Enums\EventCategory;
use App\Enums\EventSession;
use App\Enums\EventStatus;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class TableMode extends Component implements HasSchemas, HasInfolists
{
    use InteractsWithSchemas;
    use InteractsWithInfolists;

    #[Reactive]
    public array | Collection $events = [];

    public function eventsInfolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                RepeatableEntry::make('events')
                    ->table([
                        TableColumn::make(ucfirst(__('events.title'))),
                        TableColumn::make(ucfirst(__('events.date'))),
                        TableColumn::make(ucfirst(__('events.price'))),
                        TableColumn::make(ucfirst(__('events.quota'))),
                        TableColumn::make(ucfirst(__('events.category'))),
                        TableColumn::make(ucfirst(__('events.session'))),
                        TableColumn::make(ucfirst(__('events.status'))),
                        TableColumn::make(ucfirst(__('events.options'))),
                    ])
                    ->schema([
                        TextEntry::make('title'),
                        TextEntry::make('start_date')
                            ->formatStateUsing(function (Get $get) {
                                $startDate = Carbon::parse($get('start_date'))->isoFormat('DD MMM YYYY');
                                $endDate = Carbon::parse($get('end_date'))->isoFormat('DD MMM YYYY');

                                return $startDate . ' - ' . $endDate;
                            }),
                        TextEntry::make('price')
                            ->money('IDR', divideBy: 1, decimalPlaces: 2),
                        TextEntry::make('quota'),
                        TextEntry::make('category')
                            ->formatStateUsing(function (string $state) {
                                return EventCategory::tryFrom($state);
                            }),
                        TextEntry::make('session')
                            ->formatStateUsing(function (string $state) {
                                return EventSession::tryFrom($state);
                            }),
                        TextEntry::make('status')
                            ->formatStateUsing(function (string $state, Get $get) {
                                if ($get('deleted_at')) {
                                    return __('Trashed');
                                }

                                return EventStatus::tryFrom($state);
                            }),
                        Flex::make([
                            Action::make('edit')
                                ->color('warning')
                                ->icon('heroicon-o-pencil-square')
                                ->iconButton()
                                ->url(function (Get $get) {
                                    return route('dashboard.events.edit', $get('id'));
                                }),
                            Action::make('view')
                                ->color('primary')
                                ->icon('heroicon-o-magnifying-glass-plus')
                                ->iconButton()
                                ->url(function (Get $get) {
                                    return route('dashboard.events.show', $get('id'));
                                }),
                        ])
                    ])
                    ->contained(false)
                    ->hiddenLabel(),
            ])
                ->record([
                    'events' => $this->events->toArray()
                ]);
    }

    public function mount(): void
    {
        $this->fill([]);
    }

    public function render()
    {
        return view('livewire.event.table-mode');
    }
}
