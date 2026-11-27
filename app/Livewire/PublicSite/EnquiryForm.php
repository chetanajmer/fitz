<?php

namespace App\Livewire\PublicSite;

use App\Enums\EnquirySource;
use App\Models\Gym;
use App\Models\MembershipPlan;
use App\Rules\Recaptcha;
use App\Services\EnquiryService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Public enquiry form used on the QR join page and the public gym profile.
 * Always creates an Enquiry (never a member). The gym is a locked, server-resolved
 * model — the browser cannot change which gym receives the enquiry.
 */
class EnquiryForm extends Component
{
    /** reCAPTCHA v3 action name; the browser and the server must use the same value. */
    public const CAPTCHA_ACTION = 'send_enquiry';

    #[Locked]
    public Gym $gym;

    #[Locked]
    public string $source = 'qr';

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $fitness_goal = '';

    public string $interested_plan_id = '';

    /** Honeypot — must stay empty. */
    public string $website = '';

    /** Google reCAPTCHA v3 token, fetched by the browser right before each submit. */
    public string $captchaToken = '';

    public bool $submitted = false;

    public function mount(Gym $gym, string $source = 'qr'): void
    {
        $this->gym = $gym;
        $this->source = in_array($source, [EnquirySource::Qr->value, EnquirySource::Website->value], true) ? $source : EnquirySource::Qr->value;
    }

    public function submit(EnquiryService $service): void
    {
        abort_unless($this->gym->fresh()->isOperational(), 404);

        $data = $this->validate([
            'name' => 'required|string|min:2|max:120',
            'phone' => ['required', 'string', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'email' => 'nullable|email|max:255',
            'fitness_goal' => 'nullable|string|max:255',
            'interested_plan_id' => 'nullable|integer',
        ], ['phone.regex' => 'Enter a valid phone number.']);

        if ($this->website !== '') {
            $this->submitted = true; // silently drop bots

            return;
        }

        $this->verifyCaptcha();

        $key = 'public-enquiry:'.$this->gym->id.':'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['name' => 'Too many submissions. Please try again later.']);
        }
        RateLimiter::hit($key, 3600);

        $service->createFromPublic($this->gym, array_map(fn ($v) => $v === '' ? null : $v, $data), EnquirySource::from($this->source));

        $this->submitted = true;
    }

    /**
     * Checks the invisible reCAPTCHA v3 token the browser fetched on submit. It runs only after the other
     * fields pass, and the token is always cleared afterwards because Google accepts each token once.
     *
     * @throws ValidationException
     */
    protected function verifyCaptcha(): void
    {
        if (! Recaptcha::isEnabled()) {
            return;
        }

        try {
            $this->validate(['captchaToken' => ['required', 'string', new Recaptcha(self::CAPTCHA_ACTION)]], [
                'captchaToken.required' => 'Security check did not load. Please refresh the page and try again.',
            ]);
        } finally {
            $this->captchaToken = '';
        }
    }

    public function render(): View
    {
        return view('livewire.public-site.enquiry-form', [
            'plans' => MembershipPlan::forGym($this->gym)->active()->where('is_public', true)->orderBy('price')->get(['id', 'name']),
            'recaptchaSiteKey' => Recaptcha::isEnabled() ? config('services.recaptcha.site_key') : null,
            'captchaAction' => self::CAPTCHA_ACTION,
        ]);
    }
}
