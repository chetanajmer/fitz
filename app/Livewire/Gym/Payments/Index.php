<?php

namespace App\Livewire\Gym\Payments;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.gym')]
#[Title('Payments')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $method = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $this->from = $this->from ?: Carbon::today()->startOfMonth()->toDateString();
        $this->to = $this->to ?: Carbon::today()->toDateString();
    }

    public function updated(): void
    {
        $this->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'method' => ['nullable', Rule::in(PaymentMethod::values())],
        ]);
        $this->resetPage();
    }

    public function render(): View
    {
        $query = Payment::query()
            ->where('status', 'completed')
            ->whereBetween('paid_at', [Carbon::parse($this->from)->startOfDay(), Carbon::parse($this->to)->endOfDay()])
            ->when($this->method !== '', fn ($q) => $q->where('method', $this->method))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('receipt_number', 'like', "%{$this->search}%")
                ->orWhereHas('member', fn ($m) => $m->search($this->search))));

        $byMethod = (clone $query)->selectRaw('method, SUM(amount) as total, COUNT(*) as count')->groupBy('method')->get()->keyBy(fn ($r) => $r->method->value);

        return view('livewire.gym.payments.index', [
            'payments' => (clone $query)->with(['member', 'membership', 'receiver'])->latest('paid_at')->paginate(20),
            'total' => (float) $byMethod->sum('total'),
            'byMethod' => $byMethod,
        ]);
    }
}
