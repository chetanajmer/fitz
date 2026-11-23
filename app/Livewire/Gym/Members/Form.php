<?php

namespace App\Livewire\Gym\Members;

use App\Enums\DietPreference;
use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\User;
use App\Services\MemberService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.gym')]
class Form extends Component
{
    use WithFileUploads;

    public ?Member $member = null;

    /** @var array<string, mixed> */
    public array $form = [
        'name' => '', 'phone' => '', 'email' => '', 'gender' => '', 'date_of_birth' => '', 'blood_group' => '', 'height_cm' => '',
        'address' => '', 'city' => '', 'emergency_contact_name' => '', 'emergency_contact_phone' => '',
        'emergency_contact_relation' => '', 'joining_date' => '', 'fitness_goal' => '', 'diet_preference' => '', 'medical_notes' => '',
        'notes' => '', 'status' => 'active',
    ];

    public ?string $trainer_id = '';

    public string $portal_password = '';

    public $photo = null;

    public function mount(?Member $member = null): void
    {
        if ($member?->exists) {
            Gate::authorize('update', $member);
            $this->member = $member;
            foreach (array_keys($this->form) as $key) {
                $value = $member->{$key};
                $this->form[$key] = match (true) {
                    $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
                    $value instanceof \BackedEnum => $value->value,
                    default => (string) $value,
                };
            }
            $this->trainer_id = (string) $member->trainer_id;
        } else {
            Gate::authorize('create', Member::class);
            $this->form['joining_date'] = now()->toDateString();
        }
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $gymId = Auth::user()->gym_id;

        return [
            'form.name' => 'required|string|max:120',
            'form.phone' => ['required', 'string', 'min:8', 'max:20',
                Rule::unique('members', 'phone')->where('gym_id', $gymId)->whereNull('deleted_at')->ignore($this->member?->id)],
            'form.email' => 'nullable|email|max:255',
            'form.gender' => 'nullable|in:male,female,other',
            'form.date_of_birth' => 'nullable|date|before:today',
            'form.blood_group' => 'nullable|string|max:5',
            'form.height_cm' => 'nullable|numeric|between:50,250',
            'form.address' => 'nullable|string|max:255',
            'form.city' => 'nullable|string|max:80',
            'form.emergency_contact_name' => 'nullable|string|max:120',
            'form.emergency_contact_phone' => 'nullable|string|max:20',
            'form.emergency_contact_relation' => 'nullable|string|max:50',
            'form.joining_date' => 'required|date',
            'form.fitness_goal' => 'nullable|string|max:255',
            'form.diet_preference' => ['nullable', Rule::enum(DietPreference::class)],
            'form.medical_notes' => 'nullable|string|max:2000',
            'form.notes' => 'nullable|string|max:2000',
            'form.status' => ['required', Rule::enum(MemberStatus::class)],
            'trainer_id' => 'nullable|integer',
            'portal_password' => 'nullable|string|min:8',
            'photo' => 'nullable|image|max:2048',
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return collect($this->form)->keys()->mapWithKeys(fn ($k) => ["form.{$k}" => str_replace('_', ' ', $k)])->all();
    }

    public function save(MemberService $service)
    {
        $this->validate();

        if ($this->portal_password !== '' && blank($this->form['email'])) {
            $this->addError('portal_password', 'An email is required to create a member portal login.');

            return null;
        }

        $data = collect($this->form)->map(fn ($v) => $v === '' ? null : $v)->all();
        $extra = ['trainer_id' => $this->trainer_id ? (int) $this->trainer_id : null, 'portal_password' => $this->portal_password ?: null];

        if (! $this->canAssignTrainer()) {
            // A trainer limited to assigned members keeps what they register and cannot hand members away.
            $this->member ? Arr::forget($extra, 'trainer_id') : $extra['trainer_id'] = Auth::id();
        }

        if ($this->member) {
            Gate::authorize('update', $this->member);
            $member = $service->update($this->member, $data, $extra, $this->photo);
            session()->flash('success', 'Member updated.');
        } else {
            Gate::authorize('create', Member::class);
            $member = $service->create($data, $extra, $this->photo);
            session()->flash('success', "Member {$member->member_code} created. Assign a plan to activate their membership.");
        }

        return $this->redirect(route('gym.members.show', $member));
    }

    protected function canAssignTrainer(): bool
    {
        return Auth::user()->can('members.all');
    }

    public function render(): View
    {
        return view('livewire.gym.members.form', [
            'canAssignTrainer' => $this->canAssignTrainer(),
            'trainers' => User::trainersOf(Auth::user()->gym_id)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ])->title($this->member ? 'Edit '.$this->member->name : 'Add member');
    }
}
