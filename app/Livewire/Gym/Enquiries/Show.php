<?php

namespace App\Livewire\Gym\Enquiries;

use App\Enums\EnquirySource;
use App\Enums\EnquiryStatus;
use App\Enums\PaymentMethod;
use App\Models\Enquiry;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\EnquiryService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.gym')]
class Show extends Component
{
    #[Locked]
    public Enquiry $enquiry;

    public string $newStatus = '';

    public string $followUpDate = '';

    public string $note = '';

    public bool $showEdit = false;

    /** @var array<string, string> */
    public array $edit = [];

    public bool $showConvert = false;

    /** @var array<string, mixed> */
    public array $convert = [];

    public function mount(Enquiry $enquiry): void
    {
        $this->enquiry = $enquiry;
        $this->newStatus = $enquiry->status->value;
        $this->followUpDate = (string) $enquiry->follow_up_date?->toDateString();
    }

    public function updateStatus(EnquiryService $service): void
    {
        $this->validate([
            'newStatus' => ['required', Rule::in(array_keys(EnquiryStatus::manualOptions()))],
            'followUpDate' => 'nullable|date',
            'note' => 'nullable|string|max:2000',
        ]);

        $service->changeStatus($this->enquiry, EnquiryStatus::from($this->newStatus), $this->note ?: null, $this->followUpDate);
        $this->reset('note');
        $this->dispatch('notify', message: 'Enquiry updated.');
    }

    public function addNote(EnquiryService $service): void
    {
        $this->validate(['note' => 'required|string|max:2000']);
        $service->addNote($this->enquiry, $this->note);
        $this->reset('note');
        $this->dispatch('notify', message: 'Note added.');
    }

    public function openEdit(): void
    {
        $e = $this->enquiry;
        $this->edit = [
            'name' => $e->name, 'phone' => $e->phone, 'email' => (string) $e->email, 'fitness_goal' => (string) $e->fitness_goal,
            'interested_plan_id' => (string) $e->interested_plan_id, 'source' => $e->source->value, 'notes' => (string) $e->notes,
        ];
        $this->showEdit = true;
    }

    public function saveEdit(EnquiryService $service): void
    {
        $data = $this->validate([
            'edit.name' => 'required|string|max:120',
            'edit.phone' => 'required|string|min:8|max:20',
            'edit.email' => 'nullable|email|max:255',
            'edit.fitness_goal' => 'nullable|string|max:255',
            'edit.interested_plan_id' => 'nullable|integer',
            'edit.source' => ['required', Rule::enum(EnquirySource::class)],
            'edit.notes' => 'nullable|string|max:2000',
        ])['edit'];

        $service->update($this->enquiry, array_map(fn ($v) => $v === '' ? null : $v, $data));
        $this->showEdit = false;
        $this->dispatch('notify', message: 'Enquiry details saved.');
    }

    public function openConvert(): void
    {
        abort_unless(Auth::user()->can('members.manage'), 403);

        if ($this->enquiry->isConverted()) {
            $this->dispatch('notify', message: 'Already converted.', type: 'error');

            return;
        }

        $plan = $this->enquiry->interestedPlan;
        $this->convert = [
            'gender' => '', 'date_of_birth' => '', 'joining_date' => now()->toDateString(), 'trainer_id' => '',
            'plan_id' => $plan && $plan->is_active ? (string) $plan->id : '', 'start_date' => now()->toDateString(), 'discount' => '0',
            'pay_now' => false, 'amount' => $plan ? (string) $plan->price : '', 'method' => PaymentMethod::Cash->value, 'reference' => '',
        ];
        $this->showConvert = true;
    }

    public function convertToMember(EnquiryService $service)
    {
        abort_unless(Auth::user()->can('members.manage'), 403);

        $this->validate([
            'convert.gender' => 'nullable|in:male,female,other',
            'convert.date_of_birth' => 'nullable|date|before:today',
            'convert.joining_date' => 'required|date',
            'convert.trainer_id' => 'nullable|integer',
            'convert.plan_id' => 'nullable|integer',
            'convert.start_date' => 'required_with:convert.plan_id|nullable|date',
            'convert.discount' => 'nullable|numeric|min:0',
            'convert.amount' => 'nullable|numeric|min:0',
            'convert.method' => ['required', Rule::enum(PaymentMethod::class)],
            'convert.reference' => 'nullable|string|max:100',
        ], [], ['convert.joining_date' => 'joining date', 'convert.start_date' => 'start date']);

        $c = $this->convert;
        $user = Auth::user();
        // Trainers only get the parts of the conversion their sections allow.
        $c['trainer_id'] = $user->can('members.all') ? $c['trainer_id'] : $user->id;
        $c['plan_id'] = $user->can('memberships.manage') ? $c['plan_id'] : null;
        $c['pay_now'] = $c['pay_now'] && $user->can('payments.manage');

        $member = $service->convert($this->enquiry, array_filter([
            'gender' => $c['gender'] ?: null,
            'date_of_birth' => $c['date_of_birth'] ?: null,
            'joining_date' => $c['joining_date'],
            'status' => 'active',
        ]), [
            'trainer_id' => $c['trainer_id'] ? (int) $c['trainer_id'] : null,
            'plan_id' => $c['plan_id'] ? (int) $c['plan_id'] : null,
            'start_date' => $c['start_date'] ?: null,
            'discount' => (float) ($c['discount'] ?: 0),
            'payment' => $c['plan_id'] && $c['pay_now'] && (float) $c['amount'] > 0
                ? ['amount' => (float) $c['amount'], 'method' => $c['method'], 'reference' => $c['reference'] ?: null]
                : null,
        ]);

        session()->flash('success', "{$member->name} is now member {$member->member_code}.");

        return $this->redirect(route('gym.members.show', $member));
    }

    public function render(): View
    {
        $enquiry = $this->enquiry->fresh(['interestedPlan', 'convertedMember', 'activities.user']);

        return view('livewire.gym.enquiries.show', [
            'e' => $enquiry,
            'plans' => MembershipPlan::active()->orderBy('price')->get(),
            'trainers' => User::trainersOf(Auth::user()->gym_id)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'duplicates' => Member::where('phone', $enquiry->phone)->get(['id', 'name', 'member_code']),
            'canAssignTrainer' => Auth::user()->can('members.all'),
            'canAssignPlan' => Auth::user()->can('memberships.manage'),
            'canPay' => Auth::user()->can('payments.manage'),
        ])->title('Enquiry · '.$enquiry->name);
    }
}
