<?php

namespace App\Livewire\Platform\Gyms;

use App\Models\Gym;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.platform')]
#[Title('Gyms')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $today = Carbon::today()->toDateString();

        $gyms = Gym::query()
            ->with('owner')
            ->withCount([
                'members',
                'members as active_members_count' => fn ($q) => $q->whereNull('deleted_at')->where('status', 'active')
                    ->whereHas('memberships', fn ($m) => $m->withoutGlobalScopes()->valid($today)),
            ])
            ->addSelect(['outstanding' => DB::table('saas_invoices')->selectRaw('COALESCE(SUM(total - amount_paid), 0)')
                ->whereColumn('saas_invoices.gym_id', 'gyms.id')->whereIn('status', ['unpaid', 'partial'])])
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$this->search}%")
                ->orWhere('city', 'like', "%{$this->search}%")->orWhere('slug', 'like', "%{$this->search}%")
                ->orWhereHas('owner', fn ($o) => $o->where('email', 'like', "%{$this->search}%")->orWhere('name', 'like', "%{$this->search}%"))))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->latest()
            ->paginate(20);

        return view('livewire.platform.gyms.index', ['gyms' => $gyms]);
    }
}
