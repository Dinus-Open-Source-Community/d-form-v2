<?php

namespace App\Livewire\Event;

use App\Enums\EventCategory;
use App\Enums\EventSession;
use App\Enums\EventStatus;
use App\Models\Event;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\Size;
use Illuminate\Support\Facades\Cache;
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
        'statuses' => [],
        'showTrashed' => false
    ];

    public array $sort = [
        'by' => 'title',
        'order' => 'asc'
    ];

    public string $search = '';

    private int $perPage = 10;

    public function searchForm(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('search')
                ->hiddenLabel()
                ->inputMode('search')
                ->placeholder(fn () => __('Search by title'))
                ->live(debounce: 500)
                ->dehydrateStateUsing(fn ($state) => trim($state))
        ]);
    }

    public function filterAction(): Action
    {
        return Action::make('filter')
            ->modalHeading('filter & sort')
            ->color('primary')
            ->modalFooterActionsAlignment(Alignment::End)
            ->schema([
                Grid::make()
                    ->columns([
                        'default' => 2
                    ])
                    ->components([
                        CheckboxList::make('filter.categories')
                            ->label(__('events.category'))
                            ->options(EventCategory::class)
                            ->default($this->filter['categories']),
                        CheckboxList::make('filter.sessions')
                            ->label(__('events.session'))
                            ->options(EventSession::class)
                            ->default($this->filter['sessions']),
                        CheckboxList::make('filter.statuses')
                            ->label(__('events.status'))
                            ->options(EventStatus::class)
                            ->default($this->filter['statuses']),
                        Toggle::make('filter.showTrashed')
                            ->label(__("Show trashed"))
                            ->default($this->filter['showTrashed'])
                    ])
                    ->extraAttributes(['class' => 'capitalize']),
                Grid::make()
                    ->columns([
                        'default' => 2
                    ])
                    ->components([
                        Radio::make('sort.by')
                            ->label(__("Sort by"))
                            ->options([
                                'title' => __('events.title'),
                                'price' => __('events.price'),
                                'end_date' => __('events.end_date')
                            ])
                            ->default($this->sort['by']),
                        Radio::make('sort.order')
                            ->label(__('Sort order'))
                            ->options([
                                'asc' => __('Ascending'),
                                'desc' => __('Descending'),
                            ])
                            ->default($this->sort['order']),
                    ])
                    ->extraAttributes(['class' => 'capitalize'])
            ])
            ->action(function (array $data) {
                $this->filter = $data['filter'];
                $this->sort = $data['sort'];

                $this->resetPage();
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
        $hashedQuery = md5(json_encode([
            'filter' => $this->filter,
            'sort' => $this->sort,
            'search' => $this->search,
            'pagination' => [
                'per_page' => $this->perPage,
                'page' => $this->getPage()
            ]
        ]));

        return Cache::tags(['events'])->remember("list-page:events:{$hashedQuery}", 3600, function () {
            $query = Event::query();

            return $query
                // get trashed events
                ->when($this->filter['showTrashed'], fn ($q) => $q->withTrashed())

                // filtering by categories
                ->when(count($this->filter['categories']) > 0, fn ($q) => $q->whereIn('category', $this->filter['categories']))

                // filtering by sessions
                ->when(count($this->filter['sessions']) > 0, fn ($q) => $q->whereIn('session', $this->filter['sessions']))

                ->when(count($this->filter['statuses']) > 0, fn ($q) => $q->whereIn('status', $this->filter['statuses']))

                // search by title
                ->when($this->search, fn ($q) => $q->whereLike('title', "%{$this->search}%"))

                ->forListPage()

                // sorting
                ->orderBy($this->sort['by'], $this->sort['order'])
                ->paginate($this->perPage);
        });
    }

    public function resetOptions(): void
    {
        $this->fill([
            'filter' => [
                'categories' => [],
                'sessions' => [],
                'statuses' => [],
                'showTrashed' => false
            ],
            'sort' => [
                'by' => 'title',
                'order' => 'asc'
            ],
            'search' => ''
        ]);
    }

    public function setMode(string $value): void
    {
        if (!in_array($value, ['card', 'table'])) {
            return;
        }

        $this->mode = $value;
    }

    public function toggleSortOrder()
    {
        $this->sort['order'] = $this->sort['order'] === 'asc' ? 'desc' : 'asc';
    }

    public function mount(): void
    {
        $this->fill([
            'data' => collect([]),
            'mode' => 'card'
        ]);

        $this->resetOptions();
    }

    public function updatedSearch()
    {
        $this->search = trim($this->search);

        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.event.list-page');
    }
}
