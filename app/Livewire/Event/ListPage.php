<?php

namespace App\Livewire\Event;

use App\Enums\EventCategory;
use App\Enums\EventSession;
use App\Models\Event;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\Size;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class ListPage extends Component implements HasSchemas, HasActions
{
    use InteractsWithSchemas;
    use InteractsWithActions;
    use WithPagination;

    public string $mode = "card";

    public array $filter = [
        'categories' => [],
        'sessions' => [],
        'showTrashed' => false
    ];

    public array $sort = [
        'by' => 'title',
        'order' => 'asc'
    ];

    public string $search = '';

    private int $perPage = 25;

    public function searchForm(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('search')
                ->hiddenLabel()
                ->inputMode('search')
                ->placeholder(fn () => __('Search by title'))
                ->debounce(1500)
        ]);
    }

    public function filterAction(): Action
    {
        return Action::make('filter')
            ->color('primary')
            ->modalFooterActionsAlignment(Alignment::End)
            ->schema([
                Grid::make()
                    ->columns([
                        'default' => 2
                    ])
                    ->components([
                        CheckboxList::make('categories')
                            ->label(ucfirst(__('events.category')))
                            ->options(EventCategory::class)
                            ->default($this->filter['categories']),
                        CheckboxList::make('sessions')
                            ->label(ucfirst(__('events.session')))
                            ->options(EventSession::class)
                            ->default($this->filter['sessions']),
                        Toggle::make('showTrashed')
                            ->label(__("Show trashed"))
                            ->default($this->filter['showTrashed'])
                    ])
            ])
            ->action(function (array $data) {
                $this->filter = $data;
            });
    }

    public function sortingAction(): Action
    {
        return Action::make('sorting')
            ->color('ghost')
            ->extraAttributes([
                'class' => 'btn dark:btn-soft'
            ])
            ->icon('heroicon-o-bars-arrow-down')
            ->hiddenLabel()
            ->size(Size::Small)
            ->iconSize(IconSize::Large)
            ->disabled(fn () => $this->allDataCount === 0);
    }

    #[Computed]
    public function events()
    {
        $query = Event::query();

        return $query
            // get trashed events
            ->when($this->filter['showTrashed'], fn ($q) => $q->withTrashed())

            // filtering by categories
            ->when(count($this->filter['categories']) > 0, fn ($q) => $q->whereIn('category', $this->filter['categories']))

            // filtering by sessions
            ->when(count($this->filter['sessions']) > 0, fn ($q) => $q->whereIn('session', $this->filter['sessions']))

            // search by title
            ->when($this->search, fn ($q) => $q->whereLike('title', "%{$this->search}%"))

            // sorting
            ->orderBy($this->sort['by'], $this->sort['order'])
            ->paginate($this->perPage);
    }

    public function resetOptions(): void
    {
        $this->fill([
            'filter' => [
                'categories' => [],
                'sessions' => [],
                'showTrashed' => false
            ],
            'sort' => [
                'by' => 'title',
                'order' => 'asc'
            ],
            'search' => ''
        ]);
    }

    public function clearSearch(): void
    {
        $this->reset('search');
    }

    public function setMode(string $value): void
    {
        if (!in_array($value, ['card', 'table'])) {
            return;
        }

        $this->mode = $value;
    }

    public function mount(): void
    {
        $this->fill([
            'data' => collect([]),
            'mode' => 'card'
        ]);

        $this->resetOptions();
    }

    public function render()
    {
        return view('livewire.event.list-page');
    }
}
