<?php

namespace App\Livewire\Event\Form;

use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;

class FormDetail extends Component implements HasSchemas, HasInfolists
{
    use InteractsWithSchemas;
    use InteractsWithInfolists;

    public Event $event;

    public Form $form;

    public function previewTab(): Tab
    {
        return Tab::make('preview')
            ->schema([
                TextEntry::make('title'),
                TextEntry::make('description')
            ]);
    }

    public function answersTab(): Tab
    {
        return Tab::make('answers')
            ->schema([]);
    }

    public function editFormTab(): Tab
    {
        return Tab::make('edit_form')
            ->schema([

            ]);
    }

    public function formDetailInfolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('form_detail')
                    ->tabs([
                        $this->previewTab(),
                        $this->answersTab(),
                        $this->editFormTab()
                    ])
                    ->contained(false)
            ]);
    }

    public function mount(Event $event, Form $form)
    {
        // $form_fields = FormField::

        $this->fill([
            'event' => $event,
            'form' => $form
        ]);
    }

    public function render()
    {
        return view('livewire.event.form.form-detail');
    }
}
