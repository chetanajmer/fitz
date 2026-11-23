<?php

namespace App\Livewire\Gym\Content;

use App\Enums\DietPreference;
use App\Enums\FitnessPlanType;
use App\Enums\ProgramGoal;
use App\Models\FitnessPlan;
use App\Models\FitnessProgram;
use App\Models\MemberProgram;
use App\Services\ProgramService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Workout / diet programs: day-by-day schedules assigned to members. */
#[Layout('components.layouts.gym')]
#[Title('Programs')]
class Programs extends Component
{
    #[Url(except: 'workout')]
    public string $type = 'workout';

    #[Url(except: '')]
    public string $goal = '';

    #[Url(except: '')]
    public string $preference = '';

    // Import modal
    public bool $showImport = false;

    /** @var list<string> */
    public array $importKeys = [];

    // Editor modal
    public bool $showEditor = false;

    #[Locked]
    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var list<array{title: string, fitness_plan_id: string, notes: string}> */
    public array $days = [];

    public function mount(): void
    {
        abort_unless(in_array($this->type, ['workout', 'diet'], true), 404);
    }

    public function updatedType(): void
    {
        abort_unless(in_array($this->type, ['workout', 'diet'], true), 404);
        $this->reset('goal', 'preference');
    }

    protected function planType(): FitnessPlanType
    {
        return FitnessPlanType::from($this->type);
    }

    protected function authorizeManage(): void
    {
        abort_unless(Auth::user()->can('content.manage'), 403);
    }

    // ---- Import defaults ---------------------------------------------------

    public function openImport(ProgramService $programs): void
    {
        $this->authorizeManage();
        $this->importKeys = collect($programs->catalogue($this->planType()))->where('imported', false)->pluck('key')->all();
        $this->showImport = true;
    }

    public function import(ProgramService $programs): void
    {
        $this->authorizeManage();
        $result = $programs->importDefaults($this->planType(), array_values(array_map('strval', $this->importKeys)));
        $this->showImport = false;
        $this->dispatch('notify', message: "{$result['programs_created']} programs imported".($result['plans_created'] ? " (+{$result['plans_created']} plans from the library)" : '').'.');
    }

    // ---- Create / edit -------------------------------------------------------

    public function create(): void
    {
        $this->authorizeManage();
        $this->resetErrorBag();
        $this->editingId = null;
        $this->form = ['title' => '', 'summary' => '', 'goal' => ProgramGoal::forType($this->planType())[0]->value, 'level' => 'beginner',
            'diet_preference' => 'veg', 'duration_weeks' => '8', 'is_active' => true];
        $this->days = array_map(fn ($i) => ['title' => '', 'fitness_plan_id' => '', 'notes' => ''], range(1, 7));
        $this->showEditor = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeManage();
        $this->resetErrorBag();
        $program = FitnessProgram::ofType($this->planType())->with('days')->findOrFail($id);
        $this->editingId = $program->id;
        $this->form = [
            'title' => $program->title, 'summary' => (string) $program->summary, 'goal' => $program->goal->value,
            'level' => (string) $program->level, 'diet_preference' => (string) $program->diet_preference?->value,
            'duration_weeks' => (string) $program->duration_weeks, 'is_active' => $program->is_active,
        ];
        $this->days = $program->days->map(fn ($d) => [
            'title' => (string) $d->title, 'fitness_plan_id' => $d->is_rest ? '' : (string) $d->fitness_plan_id, 'notes' => (string) $d->notes,
        ])->values()->all();
        $this->showEditor = true;
    }

    public function addDay(): void
    {
        if (count($this->days) < 28) {
            $this->days[] = ['title' => '', 'fitness_plan_id' => '', 'notes' => ''];
        }
    }

    public function removeDay(int $index): void
    {
        unset($this->days[$index]);
        $this->days = array_values($this->days);
    }

    public function save(ProgramService $programs): void
    {
        $this->authorizeManage();
        $isDiet = $this->planType() === FitnessPlanType::Diet;

        $this->validate([
            'form.title' => 'required|string|max:150',
            'form.summary' => 'nullable|string|max:1000',
            'form.goal' => ['required', Rule::in(array_keys(ProgramGoal::optionsFor($this->planType())))],
            'form.level' => $isDiet ? 'nullable' : 'required|in:'.implode(',', array_keys(FitnessPlan::LEVELS)),
            'form.diet_preference' => $isDiet ? ['required', Rule::enum(DietPreference::class)] : 'nullable',
            'form.duration_weeks' => 'required|integer|min:1|max:52',
            'form.is_active' => 'boolean',
            'days' => 'required|array|min:1|max:28',
            'days.*.title' => 'nullable|string|max:120',
            'days.*.fitness_plan_id' => 'nullable|integer',
            'days.*.notes' => 'nullable|string|max:500',
        ], [], ['form.title' => 'title', 'form.goal' => 'goal', 'form.duration_weeks' => 'duration']);

        $data = $this->form;
        $data[$isDiet ? 'level' : 'diet_preference'] = null;
        $programs->save($this->editingId ? FitnessProgram::ofType($this->planType())->findOrFail($this->editingId) : null, $this->planType(), $data, $this->days);

        $this->showEditor = false;
        $this->dispatch('notify', message: 'Program saved.');
    }

    public function toggleActive(int $id): void
    {
        $this->authorizeManage();
        $program = FitnessProgram::ofType($this->planType())->findOrFail($id);
        $program->update(['is_active' => ! $program->is_active]);
    }

    public function delete(int $id): void
    {
        $this->authorizeManage();
        $program = FitnessProgram::ofType($this->planType())->findOrFail($id);

        if (MemberProgram::where('fitness_program_id', $program->id)->current()->exists()) {
            $this->dispatch('notify', message: 'Members are following this program. Deactivate it instead, or stop their assignments first.', type: 'error');

            return;
        }

        $program->delete();
        $this->dispatch('notify', message: 'Program deleted.');
    }

    public function render(ProgramService $programs): View
    {
        $type = $this->planType();

        $list = FitnessProgram::ofType($type)
            ->with(['days.plan:id,title'])
            ->withCount(['assignments as active_members' => fn ($q) => $q->current()])
            ->when($this->goal !== '', fn ($q) => $q->where('goal', $this->goal))
            ->when($this->preference !== '' && $type === FitnessPlanType::Diet, fn ($q) => $q->where('diet_preference', $this->preference))
            ->orderBy('goal')->orderBy('sort_order')->orderBy('title')
            ->get();

        return view('livewire.gym.content.programs', [
            'programs' => $list->groupBy(fn (FitnessProgram $p) => $p->goal->label()),
            'total' => FitnessProgram::ofType($type)->count(),
            'goals' => ProgramGoal::optionsFor($type),
            'catalogue' => $this->showImport ? $programs->catalogue($type) : [],
            'plans' => $this->showEditor ? FitnessPlan::ofType($type)->defaults()->orderBy('title')->get(['id', 'title']) : collect(),
            'canManage' => Auth::user()->can('content.manage'),
            'isDiet' => $type === FitnessPlanType::Diet,
        ]);
    }
}
