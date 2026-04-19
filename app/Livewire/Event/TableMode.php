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
use Livewire\Attributes\Reactive;
use Livewire\Component;

class TableMode extends Component implements HasSchemas, HasInfolists
{
    use InteractsWithSchemas;
    use InteractsWithInfolists;

    #[Reactive]
    public array $events;

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
                            ->state(fn ($record) => self::explodeCsv($record['category'] ?? ''))
                            ->formatStateUsing(fn (string $state) => EventCategory::tryFrom($state)?->getLabel() ?? $state)
                            ->badge(),
                        TextEntry::make('session')
                            ->state(fn ($record) => self::explodeCsv($record['session'] ?? ''))
                            ->formatStateUsing(fn (string $state) => EventSession::tryFrom($state)?->getLabel() ?? $state)
                            ->badge(),
                        TextEntry::make('status')
                            ->formatStateUsing(function (string $state, Get $get) {
                                if ($get('deleted_at')) {
                                    return __('Trashed');
                                }

                                return EventStatus::tryFrom($state);
                            })
                            ->badge()
                            ->color(function (Get $get) {
                                $deleted_at = $get->string('deleted_at', isNullable: true);

                                $status = (is_null($deleted_at)) ? $get->string('status') : 'trashed';

                                return match ($status) {
                                    'published' => 'primary',
                                    'draft' => 'secondary',
                                    default => 'danger'
                                };
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
                    ->hiddenLabel(),
            ])
                ->record([
                    'events' => $this->events
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

    /**
     * @return list<string>
     */
    private static function explodeCsv(mixed $raw): array
    {
        $value = is_string($raw) ? $raw : '';

        if ($value === '') {
            return [];
        }

        return collect(explode(',', $value))
            ->map(static fn (string $s) => trim($s))
            ->filter(static fn (string $s) => $s !== '')
            ->unique()
            ->values()
            ->all();
    }
}
