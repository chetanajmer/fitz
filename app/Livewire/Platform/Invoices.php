<?php

namespace App\Livewire\Platform;

use App\Enums\PaymentMethod;
use App\Models\SaasInvoice;
use App\Models\SaasPayment;
use App\Services\BillingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.platform')]
#[Title('Invoices & payments')]
class Invoices extends Component
{
    use WithPagination;

    #[Url(except: 'invoices')]
    public string $tab = 'invoices';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $search = '';

    public string $generateMonth = '';

    public bool $showPayment = false;

    #[Locked]
    public ?int $invoiceId = null;

    /** @var array<string, string> */
    public array $payment = [];

    public bool $showAdjust = false;

    public string $adjustment = '0';

    public string $adjustNotes = '';

    public function mount(): void
    {
        $this->generateMonth = Carbon::today()->subMonthNoOverflow()->format('Y-m');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'search', 'tab'], true)) {
            $this->resetPage();
        }
    }

    public function generate(BillingService $billing): void
    {
        $this->validate(['generateMonth' => 'required|date_format:Y-m']);
        $result = $billing->generateForAllGyms(Carbon::createFromFormat('Y-m-d', $this->generateMonth.'-01'));
        $this->dispatch('notify', message: "{$result['created']} invoice(s) created, {$result['skipped']} already existed.");
    }

    public function openPayment(int $id): void
    {
        $invoice = SaasInvoice::findOrFail($id);
        $this->resetErrorBag();
        $this->invoiceId = $invoice->id;
        $this->payment = ['amount' => (string) $invoice->balance(), 'method' => PaymentMethod::BankTransfer->value, 'reference' => '', 'paid_at' => now()->format('Y-m-d\TH:i'), 'notes' => ''];
        $this->showPayment = true;
    }

    public function savePayment(BillingService $billing): void
    {
        $this->validate([
            'payment.amount' => 'required|numeric|min:0.01',
            'payment.method' => ['required', Rule::enum(PaymentMethod::class)],
            'payment.reference' => 'nullable|string|max:100',
            'payment.paid_at' => 'required|date|before_or_equal:now',
            'payment.notes' => 'nullable|string|max:255',
        ], [], ['payment.amount' => 'amount', 'payment.paid_at' => 'payment date']);

        $billing->recordPayment(SaasInvoice::findOrFail($this->invoiceId), (float) $this->payment['amount'], $this->payment['method'], $this->payment['reference'], $this->payment['paid_at'], $this->payment['notes']);
        $this->showPayment = false;
        $this->dispatch('notify', message: 'Payment recorded.');
    }

    public function openAdjust(int $id): void
    {
        $invoice = SaasInvoice::findOrFail($id);
        $this->invoiceId = $invoice->id;
        $this->adjustment = (string) $invoice->adjustment;
        $this->adjustNotes = (string) $invoice->notes;
        $this->showAdjust = true;
    }

    public function saveAdjust(BillingService $billing): void
    {
        $this->validate(['adjustment' => 'required|numeric|min:-1000000|max:1000000', 'adjustNotes' => 'nullable|string|max:500']);
        $billing->adjust(SaasInvoice::findOrFail($this->invoiceId), (float) $this->adjustment, $this->adjustNotes ?: null);
        $this->showAdjust = false;
        $this->dispatch('notify', message: 'Invoice adjusted.');
    }

    public function void(int $id, BillingService $billing): void
    {
        $billing->void(SaasInvoice::findOrFail($id));
        $this->dispatch('notify', message: 'Invoice voided.');
    }

    public function render(): View
    {
        $invoices = $payments = null;

        if ($this->tab === 'payments') {
            $payments = SaasPayment::with(['gym', 'invoice', 'recorder'])
                ->when($this->search !== '', fn ($q) => $q->whereHas('gym', fn ($g) => $g->where('name', 'like', "%{$this->search}%")))
                ->latest('paid_at')->paginate(20);
        } else {
            $invoices = SaasInvoice::with('gym')
                ->when($this->status === 'outstanding', fn ($q) => $q->outstanding())
                ->when($this->status === 'overdue', fn ($q) => $q->outstanding()->whereDate('due_on', '<', Carbon::today()))
                ->when(in_array($this->status, ['paid', 'void', 'unpaid', 'partial'], true), fn ($q) => $q->where('status', $this->status))
                ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('invoice_number', 'like', "%{$this->search}%")
                    ->orWhereHas('gym', fn ($g) => $g->where('name', 'like', "%{$this->search}%"))))
                ->latest('period_start')->latest('id')->paginate(20);
        }

        return view('livewire.platform.invoices', [
            'invoices' => $invoices,
            'payments' => $payments,
            'outstanding' => (float) SaasInvoice::outstanding()->sum(DB::raw('total - amount_paid')),
            'selected' => $this->invoiceId ? SaasInvoice::with('gym')->find($this->invoiceId) : null,
        ]);
    }
}
