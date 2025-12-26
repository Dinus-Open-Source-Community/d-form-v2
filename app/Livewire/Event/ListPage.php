<?php

namespace App\Livewire\Event;

use App\Models\Event;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\Size;
use Livewire\Component;

class ListPage extends Component implements HasSchemas, HasActions
{
    use InteractsWithSchemas;
    use InteractsWithActions;

    public string $mode = "card";

    public array $filter = [
        'category' => 0,
        'session' => null,
    ];

    public array $sort = [
        'by' => 'title',
        'order' => 'asc'
    ];

    public string $search = '';

    public mixed $data;
    public int $dataCount = 0;
    public int $allDataCount = 0;

    public function searchForm(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('search')
                ->hiddenLabel()
                ->inputMode('search')
                ->placeholder(fn () => __('Search by title'))
                ->debounce(1500)
                ->disabled(function () {
                    return $this->allDataCount === 0;
                }),
        ]);
    }

    public function filterAction(): Action
    {
        return Action::make('filter')
            ->color('ghost')
            ->extraAttributes([
                'class' => 'btn dark:btn-soft'
            ])
            ->icon('heroicon-o-funnel')
            ->hiddenLabel()
            ->size(Size::Small)
            ->iconSize(IconSize::Large)
            ->disabled(function () {
                return $this->allDataCount === 0;
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
        $this->data = Event::query()
            ->orderBy($this->sort['by'], $this->sort['order'])
            ->whereLike('title', '%' . $this->search . '%')
            ->get();
    }

    public function resetOptions(): void
    {
        $this->fill([
            'filter' => [
                'category' => 0,
                'session' => null
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
            'allDataCount' => Event::count()
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
