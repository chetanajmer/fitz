<?php

namespace App\Livewire\Gym\Members;

use App\Services\MemberImportService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.gym')]
#[Title('Import members')]
class Import extends Component
{
    use WithFileUploads;

    public $file = null;

    /** @var array{imported:int, skipped:int, errors:list<string>}|null */
    public ?array $result = null;

    public function import(MemberImportService $importer): void
    {
        $this->validate(['file' => 'required|file|max:5120|extensions:csv,xlsx,txt']);

        $extension = strtolower($this->file->getClientOriginalExtension());
        $this->result = $importer->import($this->file->getRealPath(), $extension === 'xlsx' ? 'xlsx' : 'csv');
        $this->reset('file');

        $this->dispatch('notify', message: "{$this->result['imported']} member(s) imported.", type: $this->result['imported'] > 0 ? 'success' : 'error');
    }

    public function render(): View
    {
        return view('livewire.gym.members.import', ['columns' => MemberImportService::COLUMNS]);
    }
}
