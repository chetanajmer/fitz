<?php

namespace Tests\Feature;

use App\Enums\EnquiryStatus;
use App\Livewire\PublicSite\EnquiryForm;
use App\Models\Enquiry;
use App\Models\Member;
use App\Rules\Recaptcha;
use App\Services\EnquiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EnquiryFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_join_page_creates_an_enquiry_not_a_member(): void
    {
        $gym = $this->gymOf($this->createGymOwner());

        $this->get(route('gym.join', $gym->join_token))->assertOk()->assertSee($gym->name);

        Livewire::test(EnquiryForm::class, ['gym' => $gym, 'source' => 'qr'])
            ->set('name', 'Walk In')
            ->set('phone', '9876543210')
            ->set('fitness_goal', 'Weight loss')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $enquiry = Enquiry::forGym($gym)->sole();
        $this->assertSame(EnquiryStatus::New, $enquiry->status);
        $this->assertSame('qr', $enquiry->source->value);
        $this->assertSame(0, Member::forGym($gym)->count());
    }

    public function test_enquiry_requires_a_captcha_token_when_recaptcha_is_configured(): void
    {
        $this->enableRecaptcha();
        Http::fake();
        $gym = $this->gymOf($this->createGymOwner());

        Livewire::test(EnquiryForm::class, ['gym' => $gym, 'source' => 'website'])
            ->set('name', 'Walk In')
            ->set('phone', '9876543210')
            ->call('submit')
            ->assertHasErrors(['captchaToken'])
            ->assertSet('submitted', false);

        Http::assertNothingSent();
        $this->assertSame(0, Enquiry::forGym($gym)->count());
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function rejectedCaptchaResponses(): array
    {
        return [
            'invalid token' => [['success' => false, 'error-codes' => ['invalid-input-response']]],
            'low score' => [['success' => true, 'score' => 0.3, 'action' => 'send_enquiry', 'hostname' => 'localhost']],
            'wrong action' => [['success' => true, 'score' => 0.9, 'action' => 'login', 'hostname' => 'localhost']],
            'wrong hostname' => [['success' => true, 'score' => 0.9, 'action' => 'send_enquiry', 'hostname' => 'evil.example.com']],
            'missing score' => [['success' => true, 'action' => 'send_enquiry', 'hostname' => 'localhost']],
            'google server error' => [[]],
        ];
    }

    public function test_enquiry_is_rejected_gracefully_when_google_times_out(): void
    {
        $this->enableRecaptcha();
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));
        $gym = $this->gymOf($this->createGymOwner());

        Livewire::test(EnquiryForm::class, ['gym' => $gym, 'source' => 'website'])
            ->set('name', 'Walk In')
            ->set('phone', '9876543210')
            ->set('captchaToken', 'some-token')
            ->call('submit')
            ->assertHasErrors(['captchaToken' => 'Could not run the security check. Please try again in a moment.'])
            ->assertSet('submitted', false);

        $this->assertSame(0, Enquiry::forGym($gym)->count());
    }

    /**
     * @param  array<string, mixed>  $googleResponse
     */
    #[DataProvider('rejectedCaptchaResponses')]
    public function test_enquiry_is_rejected_when_the_captcha_does_not_pass(array $googleResponse): void
    {
        $this->enableRecaptcha();
        Http::fake([Recaptcha::VERIFY_URL => Http::response($googleResponse)]);
        $gym = $this->gymOf($this->createGymOwner());

        Livewire::test(EnquiryForm::class, ['gym' => $gym, 'source' => 'website'])
            ->set('name', 'Walk In')
            ->set('phone', '9876543210')
            ->set('captchaToken', 'some-token')
            ->call('submit')
            ->assertHasErrors(['captchaToken'])
            ->assertSet('captchaToken', '')
            ->assertSet('submitted', false);

        $this->assertSame(0, Enquiry::forGym($gym)->count());
    }

    public function test_enquiry_is_created_when_the_captcha_passes(): void
    {
        $this->enableRecaptcha();
        Http::fake([Recaptcha::VERIFY_URL => Http::response(['success' => true, 'score' => 0.9, 'action' => 'send_enquiry', 'hostname' => parse_url(config('app.url'), PHP_URL_HOST)])]);
        $gym = $this->gymOf($this->createGymOwner());

        Livewire::test(EnquiryForm::class, ['gym' => $gym, 'source' => 'website'])
            ->assertSee('recaptcha/api.js?render=')
            ->set('name', 'Walk In')
            ->set('phone', '9876543210')
            ->set('captchaToken', 'good-token')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        Http::assertSent(fn (Request $request) => $request->url() === Recaptcha::VERIFY_URL
            && $request['secret'] === 'test-secret'
            && $request['response'] === 'good-token');
        $this->assertSame(1, Enquiry::forGym($gym)->count());
    }

    public function test_captcha_is_not_verified_when_other_fields_are_invalid(): void
    {
        $this->enableRecaptcha();
        Http::fake();
        $gym = $this->gymOf($this->createGymOwner());

        Livewire::test(EnquiryForm::class, ['gym' => $gym, 'source' => 'website'])
            ->set('name', '')
            ->set('phone', '9876543210')
            ->set('captchaToken', 'good-token')
            ->call('submit')
            ->assertHasErrors(['name']);

        Http::assertNothingSent();
    }

    public function test_form_has_no_captcha_when_keys_are_not_configured(): void
    {
        $gym = $this->gymOf($this->createGymOwner());

        Livewire::test(EnquiryForm::class, ['gym' => $gym, 'source' => 'website'])
            ->assertDontSee('recaptcha/api.js')
            ->assertSee('Send enquiry');
    }

    private function enableRecaptcha(): void
    {
        config(['services.recaptcha.site_key' => 'test-site-key', 'services.recaptcha.secret_key' => 'test-secret']);
    }

    public function test_invalid_join_token_and_inactive_gym_return_404(): void
    {
        $gym = $this->gymOf($this->createGymOwner());

        $this->get('/join/'.str_repeat('x', 40))->assertNotFound();

        $gym->forceFill(['status' => 'suspended', 'is_active' => false])->save();
        $this->get(route('gym.join', $gym->join_token))->assertNotFound();
        $this->get(route('gym.public', $gym->slug))->assertNotFound();
    }

    public function test_enquiry_converts_once_and_keeps_history(): void
    {
        $owner = $this->createGymOwner();
        $gym = $this->gymOf($owner);
        $plan = $this->createPlan($gym);
        $this->actingAs($owner);

        $service = app(EnquiryService::class);
        $enquiry = $service->create(['name' => 'Lead One', 'phone' => '9123456789', 'interested_plan_id' => $plan->id]);
        $service->changeStatus($enquiry, EnquiryStatus::Interested, 'Visited the gym');

        $member = $service->convert($enquiry->fresh(), ['joining_date' => now()->toDateString()], [
            'plan_id' => $plan->id, 'payment' => ['amount' => 400, 'method' => 'upi'],
        ]);

        $enquiry->refresh();
        $this->assertSame(EnquiryStatus::Converted, $enquiry->status);
        $this->assertSame($member->id, $enquiry->converted_member_id);
        $this->assertSame($enquiry->id, $member->enquiry_id);
        $this->assertSame('Lead One', $member->name);
        $this->assertSame(1, $member->memberships()->count());
        $this->assertEquals(600.0, $member->pendingAmount());
        $this->assertSame(['created', 'status_changed', 'converted'], $enquiry->activities()->reorder()->oldest('id')->pluck('type')->all());

        $this->expectException(ValidationException::class);
        $service->convert($enquiry, ['joining_date' => now()->toDateString()]);
    }

    public function test_converted_enquiry_status_cannot_be_changed(): void
    {
        $owner = $this->createGymOwner();
        $this->actingAs($owner);
        $service = app(EnquiryService::class);
        $enquiry = $service->create(['name' => 'Lead', 'phone' => '9123456780']);
        $service->convert($enquiry, ['joining_date' => now()->toDateString()]);

        $this->expectException(ValidationException::class);
        $service->changeStatus($enquiry->fresh(), EnquiryStatus::Contacted);
    }
}
