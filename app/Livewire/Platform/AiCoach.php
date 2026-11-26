<?php

namespace App\Livewire\Platform;

use App\Models\AiReport;
use App\Models\Gym;
use App\Models\GymAiSetting;
use App\Models\PlatformSetting;
use App\Services\Ai\OpenRouterClient;
use App\Services\AiAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/** Super Admin: the platform AI switch, model and credit costs, and each gym's AI limits and usage. */
#[Layout('components.layouts.platform')]
#[Title('AI Coach')]
class AiCoach extends Component
{
    /** @var array<string, string> */
    public array $settings = [];

    /** A new OpenRouter key typed by the Super Admin. Never filled with the saved one. */
    public string $apiKey = '';

    public ?int $editingGymId = null;

    /** @var array{allowed: bool, monthly_credits: string, member_ceiling: string, photos_allowed: bool} */
    public array $gymForm = [];

    protected const KEYS = ['ai_enabled', 'ai_model', 'ai_credits_progress', 'ai_credits_photo_comparison', 'ai_default_gym_credits', 'ai_default_member_ceiling'];

    public function mount(): void
    {
        foreach (self::KEYS as $key) {
            $this->settings[$key] = (string) PlatformSetting::get($key);
        }
    }

    public function save(OpenRouterClient $client): void
    {
        $this->apiKey = trim($this->apiKey);
        $data = $this->validate([
            'settings.ai_enabled' => 'required|in:0,1',
            'settings.ai_model' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9._-]+\/[a-z0-9._:-]+$/i'],
            'settings.ai_credits_progress' => 'required|integer|min:1|max:20',
            'settings.ai_credits_photo_comparison' => 'required|integer|min:1|max:20',
            'settings.ai_default_gym_credits' => 'required|integer|min:0|max:100000',
            'settings.ai_default_member_ceiling' => 'required|integer|min:1|max:100',
            'apiKey' => 'nullable|string|min:20|max:200',
        ], ['settings.ai_model.regex' => 'Use an OpenRouter model id like openai/gpt-6-luna.'], ['apiKey' => 'API key'])['settings'];

        try {
            if ($this->apiKey !== '' && ! $client->keyWorks($this->apiKey)) {
                $this->addError('apiKey', 'OpenRouter did not accept this key.');

                return;
            }
            if (! in_array($data['ai_model'], $client->suitableModels(), true)) {
                $this->addError('settings.ai_model', 'This model cannot be used: it must exist on OpenRouter, read images, support structured output and have a provider that keeps no data.');

                return;
            }
        } catch (Throwable) {
            $this->addError('settings.ai_model', 'Could not reach OpenRouter to check the settings. Nothing was saved; please try again.');

            return;
        }

        PlatformSetting::put($data);
        if ($this->apiKey !== '') {
            $client->saveKey($this->apiKey);
        }
        $this->reset('apiKey');
        $this->dispatch('notify', message: 'AI settings saved.');
    }

    /** Go back to OPENROUTER_API_KEY from .env. */
    public function removeKey(OpenRouterClient $client): void
    {
        $client->saveKey(null);
        $this->dispatch('notify', message: 'Saved API key removed.');
    }

    public function editGym(int $gymId, AiAccessService $access): void
    {
        $settings = $access->settingsFor(Gym::findOrFail($gymId));
        $this->editingGymId = $gymId;
        $this->gymForm = [
            'allowed' => $settings->allowed,
            'monthly_credits' => (string) $settings->monthly_credits,
            'member_ceiling' => (string) $settings->member_ceiling,
            'photos_allowed' => $settings->photos_allowed,
        ];
        $this->resetValidation();
    }

    public function saveGym(AiAccessService $access): void
    {
        $data = $this->validate([
            'gymForm.allowed' => 'boolean',
            'gymForm.monthly_credits' => 'required|integer|min:0|max:100000',
            'gymForm.member_ceiling' => 'required|integer|min:1|max:100',
            'gymForm.photos_allowed' => 'boolean',
        ], [], ['gymForm.monthly_credits' => 'monthly credits', 'gymForm.member_ceiling' => 'credits per member'])['gymForm'];

        $settings = $access->settingsFor(Gym::findOrFail($this->editingGymId));
        $settings->fill($data);
        // A lower ceiling also lowers the gym's own per-member limit.
        $settings->member_limit = min($settings->member_limit, (int) $data['member_ceiling']);
        $settings->save();

        $this->editingGymId = null;
        $this->dispatch('notify', message: 'Gym AI limits saved.');
    }

    public function render(OpenRouterClient $client): View
    {
        $start = Carbon::now()->startOfMonth();
        $usage = AiReport::query()->where('status', '!=', AiReport::FAILED)->where('created_at', '>=', $start)
            ->groupBy('gym_id')->select('gym_id', DB::raw('SUM(credits) as credits'), DB::raw('COUNT(*) as reports'), DB::raw('SUM(cost_usd) as cost_usd'))
            ->get()->keyBy('gym_id');

        return view('livewire.platform.ai-coach', [
            'configured' => $client->isConfigured(),
            'savedKey' => $client->savedKey(),
            'envKey' => filled(config('services.openrouter.key')),
            'models' => rescue(fn () => $client->suitableModels(), [], false),
            'gyms' => Gym::orderBy('name')->get(['id', 'name', 'city', 'status']),
            'gymSettings' => GymAiSetting::query()->get()->keyBy('gym_id'),
            'usage' => $usage,
            'totalCost' => (float) $usage->sum('cost_usd'),
        ]);
    }
}
