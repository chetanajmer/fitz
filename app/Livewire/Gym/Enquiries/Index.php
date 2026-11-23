<?php

namespace App\Livewire\Gym\Enquiries;

use App\Enums\EnquirySource;
use App\Models\Enquiry;
use App\Models\MembershipPlan;
use App\Services\EnquiryService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.gym')]
#[Title('Enquiries')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $source = '';

    #[Url(except: '')]
    public string $followUp = '';

    public bool $showForm = false;

    /** @var array<string, string> */
    public array $form = [];

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'source', 'followUp'], true)) {
            $this->resetPage();
        }
    }

    public function create(): void
    {
        $this->resetErrorBag();
        $this->form = [
            'name' => '', 'phone' => '', 'email' => '', 'fitness_goal' => '', 'interested_plan_id' => '',
            'source' => EnquirySource::WalkIn->value, 'follow_up_date' => Carbon::tomorrow()->toDateString(), 'notes' => '',
        ];
        $this->showForm = true;
    }

    public function save(EnquiryService $service): void
    {
        $data = $this->validate([
            'form.name' => 'required|string|max:120',
            'form.phone' => 'required|string|min:8|max:20',
            'form.email' => 'nullable|email|max:255',
            'form.fitness_goal' => 'nullable|string|max:255',
            'form.interested_plan_id' => 'nullable|integer',
            'form.source' => ['required', Rule::enum(EnquirySource::class)],
            'form.follow_up_date' => 'nullable|date',
            'form.notes' => 'nullable|string|max:2000',
        ], [], ['form.name' => 'name', 'form.phone' => 'phone'])['form'];

        $enquiry = $service->create(array_map(fn ($v) => $v === '' ? null : $v, $data));

        $this->showForm = false;
        $this->dispatch('notify', message: "Enquiry from {$enquiry->name} added.");
    }

    public function render(): View
    {
        $today = Carbon::today()->toDateString();

        $enquiries = Enquiry::query()
            ->with('interestedPlan')
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")->orWhere('email', 'like', "%{$this->search}%")))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->source !== '', fn ($q) => $q->where('source', $this->source))
            ->when($this->followUp === 'due', fn ($q) => $q->open()->whereDate('follow_up_date', '<=', $today))
            ->when($this->followUp === 'upcoming', fn ($q) => $q->open()->whereDate('follow_up_date', '>', $today))
            ->latest()
            ->paginate(15);

        $counts = Enquiry::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('livewire.gym.enquiries.index', [
            'enquiries' => $enquiries,
            'counts' => $counts,
            'plans' => MembershipPlan::active()->orderBy('price')->get(['id', 'name']),
            'dueCount' => Enquiry::open()->whereDate('follow_up_date', '<=', $today)->count(),
        ]);
    }
}
