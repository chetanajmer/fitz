<?php

namespace App\Livewire\Gym\Trainers;

use App\Models\User;
use App\Services\TrainerService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.gym')]
#[Title('Trainers')]
class Index extends Component
{
    use WithFileUploads;

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    /** @var array<string, string> */
    public array $form = [];

    public $photo = null;

    public function create(): void
    {
        $this->resetErrorBag();
        $this->reset('photo', 'editingId');
        $this->form = ['name' => '', 'email' => '', 'phone' => '', 'specialization' => '', 'bio' => '', 'status' => 'active', 'password' => ''];
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $trainer = $this->findTrainer($id);
        $this->resetErrorBag();
        $this->reset('photo');
        $this->editingId = $trainer->id;
        $this->form = [
            'name' => $trainer->name, 'email' => $trainer->email, 'phone' => (string) $trainer->phone,
            'specialization' => (string) $trainer->specialization, 'bio' => (string) $trainer->bio, 'status' => $trainer->status, 'password' => '',
        ];
        $this->showForm = true;
    }

    public function save(TrainerService $service): void
    {
        $data = $this->validate([
            'form.name' => 'required|string|max:120',
            'form.email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'form.phone' => 'nullable|string|max:20',
            'form.specialization' => 'nullable|string|max:120',
            'form.bio' => 'nullable|string|max:1000',
            'form.status' => 'required|in:active,inactive',
            'form.password' => [$this->editingId ? 'nullable' : 'required', Password::min(8)],
            'photo' => 'nullable|image|max:2048',
        ], [], ['form.name' => 'name', 'form.email' => 'email', 'form.password' => 'password'])['form'];

        if ($this->editingId) {
            $service->update($this->findTrainer($this->editingId), $data, $this->photo);
        } else {
            $service->create($data, $this->photo);
        }

        $this->showForm = false;
        $this->dispatch('notify', message: 'Trainer saved.');
    }

    public function toggleStatus(int $id, TrainerService $service): void
    {
        $trainer = $this->findTrainer($id);
        $service->setStatus($trainer, $trainer->status === 'active' ? 'inactive' : 'active');
    }

    public function delete(int $id, TrainerService $service): void
    {
        $service->delete($this->findTrainer($id));
        $this->dispatch('notify', message: 'Trainer removed. Their members are now unassigned.');
    }

    protected function findTrainer(int $id): User
    {
        return User::trainersOf(Auth::user()->gym_id)->findOrFail($id);
    }

    public function render(): View
    {
        return view('livewire.gym.trainers.index', [
            'trainers' => User::trainersOf(Auth::user()->gym_id)->withCount(['assignedMembers', 'permissions'])->orderBy('name')->get(),
        ]);
    }
}
