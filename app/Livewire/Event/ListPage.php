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
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\Size;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

class ListPage extends Component implements HasSchemas, HasActions
{
    use InteractsWithSchemas;
    use InteractsWithActions;

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

    public Collection $data;

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

                $this->queryEvents();
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
            ->disabled(function () {
                return $this->allDataCount === 0;
            });
    }

    public function queryEvents(): void
    {
        $query = Event::query();

        if ($this->filter['showTrashed']) {
            $query = $query->withTrashed();
        }

        $this->data = $query
            ->orderBy($this->sort['by'], $this->sort['order'])
            ->where(function (Builder $query) {
                if (count($this->filter['categories']) > 0) {
                    $query = $query->whereIn('category', $this->filter['categories']);
                }

                if (count($this->filter['sessions']) > 0) {
                    $query = $query->whereIn('session', $this->filter['sessions']);
                }

                return $query
                    ->whereLike('title', '%' . $this->search . '%');
            })
            ->get();
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

        $this->queryEvents();
    }

    public function mount(): void
    {
        $this->fill([
            'data' => collect([]),
        ]);

        $this->resetOptions();

        $this->queryEvents();
    }

    public function updatedSearch(): void
    {
        $this->queryEvents();
    }

    public function render()
    {
        return view('livewire.event.list-page');
    }
}
