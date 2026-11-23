<?php

namespace Tests\Feature;

use App\Contracts\SmsSender;
use App\Enums\FitnessPlanType;
use App\Livewire\Gym\Content\Editor;
use App\Livewire\Gym\Content\Library;
use App\Models\FitnessPlan;
use App\Models\Gym;
use App\Models\Member;
use App\Models\PlanCategory;
use App\Services\FitnessPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FitnessContentTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->gym = $this->gymOf($this->createGymOwner());
    }

    protected ?SmsSender $sms = null;

    protected function memberToken(Member $member): string
    {
        // One fake per test: the router caches controller instances, so a later swap wouldn't be seen.
        if (! $this->sms) {
            $this->sms = new class implements SmsSender
            {
                public string $code = '';

                public function send(string $phone, string $message): void
                {
                    $this->code = substr($message, 0, 6);
                }
            };
            $this->app->instance(SmsSender::class, $this->sms);
        }

        $this->postJson('/api/v1/auth/send-otp', ['phone' => $member->phone])->assertOk();

        return $this->postJson('/api/v1/auth/verify-otp', ['phone' => $member->phone, 'otp' => $this->sms->code])->json('data.token');
    }

    /** Create a plan through the service inside the gym context. */
    protected function makePlan(string $title, ?Member $member = null, array $extra = [], string $type = 'diet'): FitnessPlan
    {
        return $this->inGym($this->gym, fn () => app(FitnessPlanService::class)->savePlan(
            null, FitnessPlanType::from($type), ['title' => $title] + $extra,
            [['title' => 'Breakfast', 'subtitle' => '8:00 AM', 'body' => "Oats\nEggs"], ['title' => 'Lunch', 'body' => 'Dal, rice']],
            $member,
        ));
    }

    public function test_admin_builds_category_and_default_plan_with_photos_and_text(): void
    {
        $this->actingAs($this->gym->owner);

        Livewire::test(Library::class, ['type' => 'diet'])
            ->call('editCategory')
            ->set('categoryForm.name', 'Weight Loss')
            ->set('categoryImage', UploadedFile::fake()->image('cat.jpg'))
            ->call('saveCategory')
            ->assertHasNoErrors();
        $category = PlanCategory::sole();
        $this->assertSame('diet', $category->type->value);
        Storage::disk('public')->assertExists($category->image_path);

        $editor = Livewire::test(Editor::class, ['type' => 'diet'])
            ->set('form.title', '1500 kcal fat loss')
            ->set('form.plan_category_id', (string) $category->id)
            ->set('form.daily_calories', '1500')
            ->set('cover', UploadedFile::fake()->image('cover.jpg'));

        $firstKey = $editor->get('sections')[0]['key'];
        $editor->set('sections.0.title', 'Breakfast')
            ->set('sections.0.subtitle', '8:00 AM')
            ->set('sections.0.body', "2 eggs\n1 bowl oats")
            ->set("uploads.{$firstKey}", UploadedFile::fake()->image('breakfast.jpg'))
            ->call('addSection')
            ->set('sections.1.title', 'Dinner')
            ->set('sections.1.body', 'Grilled paneer & salad')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $plan = FitnessPlan::with('sections')->sole();
        $this->assertNull($plan->member_id);
        $this->assertSame(1500, $plan->daily_calories);
        $this->assertSame(['Breakfast', 'Dinner'], $plan->sections->pluck('title')->all());
        Storage::disk('public')->assertExists($plan->cover_image_path);
        Storage::disk('public')->assertExists($plan->sections[0]->image_path);
        $this->assertNull($plan->sections[1]->image_path);

        // Edit: drop the first section (its photo is deleted) and keep the second.
        $oldImage = $plan->sections[0]->image_path;
        $editor = Livewire::test(Editor::class, ['plan' => $plan]);
        $editor->call('removeSection', $editor->get('sections')[0]['key'])->call('save')->assertHasNoErrors();
        $this->assertSame(['Dinner'], $plan->fresh()->sections->pluck('title')->all());
        Storage::disk('public')->assertMissing($oldImage);
    }

    public function test_members_see_defaults_plus_only_their_own_custom_plans(): void
    {
        $alice = $this->createMember($this->gym, ['phone' => '9000000501']);
        $bob = $this->createMember($this->gym, ['phone' => '9000000502']);
        $category = $this->inGym($this->gym, fn () => PlanCategory::create(['type' => 'diet', 'name' => 'Weight Loss']));

        $default = $this->makePlan('Default fat loss', null, ['plan_category_id' => $category->id]);
        $loose = $this->makePlan('Uncategorised plan');
        $this->makePlan('Draft plan', null, ['is_published' => false]);
        $aliceOnly = $this->makePlan('Alice custom chart', $alice);
        $this->makePlan('Leg day', null, [], 'workout');

        $token = $this->memberToken($alice);
        $this->withToken($token)->getJson('/api/v1/me/plans?type=diet')
            ->assertOk()
            ->assertJsonPath('data.custom.0.title', 'Alice custom chart')
            ->assertJsonPath('data.custom.0.is_custom', true)
            ->assertJsonPath('data.categories.0.name', 'Weight Loss')
            ->assertJsonPath('data.categories.0.plans.0.id', $default->id)
            ->assertJsonPath('data.uncategorized.0.id', $loose->id)
            ->assertJsonCount(1, 'data.custom')
            ->assertJsonCount(1, 'data.uncategorized'); // draft hidden, workout not mixed in

        $this->withToken($token)->getJson("/api/v1/me/plans/{$aliceOnly->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Alice custom chart')
            ->assertJsonPath('data.sections.0.title', 'Breakfast')
            ->assertJsonPath('data.sections.0.subtitle', '8:00 AM')
            ->assertJsonPath('data.sections.0.body', "Oats\nEggs");

        $this->withToken($token)->getJson('/api/v1/me/plans?type=workout')->assertOk()->assertJsonPath('data.uncategorized.0.title', 'Leg day');

        // Bob cannot open Alice's plan, and doesn't get it in his list.
        $this->app['auth']->forgetGuards();
        $bobToken = $this->memberToken($bob);
        $this->withToken($bobToken)->getJson("/api/v1/me/plans/{$aliceOnly->id}")->assertNotFound()->assertJsonPath('code', 'PLAN_NOT_FOUND');
        $this->withToken($bobToken)->getJson('/api/v1/me/plans?type=diet')->assertJsonCount(0, 'data.custom');
    }

    public function test_plans_never_leak_across_gyms(): void
    {
        $otherGym = $this->gymOf($this->createGymOwner('Other'));
        $plan = $this->makePlan('Gym A plan');
        $outsider = $this->createMember($otherGym, ['phone' => '9000000503']);

        $this->withToken($this->memberToken($outsider))->getJson("/api/v1/me/plans/{$plan->id}")->assertNotFound();

        $this->actingAs($otherGym->owner)->get(route('gym.content.show', $plan))->assertNotFound();
    }

    public function test_copy_default_plan_to_member_creates_editable_custom_copy(): void
    {
        $member = $this->createMember($this->gym);
        $default = $this->makePlan('Template');
        $this->actingAs($this->gym->owner);

        Livewire::test(Library::class, ['type' => 'diet'])
            ->call('openSend', $default->id)
            ->call('sendTo', $member->id)
            ->assertRedirect();

        $copy = FitnessPlan::where('member_id', $member->id)->with('sections')->sole();
        $this->assertSame($default->id, $copy->copied_from_id);
        $this->assertSame(2, $copy->sections->count());
        $this->assertSame(2, $default->fresh()->sections()->count(), 'template untouched');
    }

    public function test_trainer_can_only_customise_for_assigned_members(): void
    {
        $trainer = $this->createTrainer($this->gym);
        $mine = $this->createMember($this->gym, [], ['trainer_id' => $trainer->id]);
        $other = $this->createMember($this->gym);
        $otherPlan = $this->makePlan('Other member plan', $other);
        $default = $this->makePlan('Default');

        $this->actingAs($trainer);

        $this->get(route('gym.content.index', 'diet'))->assertOk();
        $this->get(route('gym.content.show', $default))->assertOk();
        $this->get(route('gym.content.create', ['type' => 'diet', 'member' => $mine->id]))->assertOk();
        $this->get(route('gym.content.create', ['type' => 'diet', 'member' => $other->id]))->assertNotFound(); // not visible to trainer
        $this->get(route('gym.content.create', ['type' => 'diet']))->assertForbidden();                         // default plans
        $this->get(route('gym.content.edit', $default))->assertForbidden();
        $this->get(route('gym.content.edit', $otherPlan))->assertForbidden();

        Livewire::test(Library::class, ['type' => 'diet'])->call('editCategory')->assertForbidden();

        $this->get(route('gym.content.create', ['type' => 'workout', 'member' => $mine->id]));
        Livewire::withQueryParams(['member' => $mine->id])->test(Editor::class, ['type' => 'workout'])
            ->set('form.title', 'Push day')
            ->set('sections.0.title', 'Bench press')
            ->set('sections.0.subtitle', '4 × 10')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Push day', FitnessPlan::where('member_id', $mine->id)->sole()->title);
    }

    public function test_pages_render_for_staff_and_member_portal(): void
    {
        $member = $this->createMember($this->gym, ['email' => 'plans@example.test'], ['portal_password' => 'password']);
        $default = $this->makePlan('Default chart');
        $custom = $this->makePlan('Your personal chart', $member);
        $other = $this->makePlan('Someone else', $this->createMember($this->gym));

        $this->actingAs($this->gym->owner);
        foreach ([
            route('gym.content.index', 'diet'), route('gym.content.index', ['type' => 'diet', 'tab' => 'custom']),
            route('gym.content.index', ['type' => 'workout', 'tab' => 'categories']), route('gym.content.create', 'workout'),
            route('gym.content.show', $default), route('gym.content.edit', $custom),
            route('gym.members.show', [$member, 'tab' => 'plans']),
        ] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get(route('gym.members.show', [$member, 'tab' => 'plans']))->assertSee('Your personal chart');

        $this->actingAs($member->user);
        $this->get(route('portal.plans', 'diet'))->assertOk()->assertSee('Made for you')->assertSee('Your personal chart')->assertSee('Default chart')->assertDontSee('Someone else');
        $this->get(route('portal.plans.show', $custom->id))->assertOk()->assertSee('Breakfast');
        $this->get(route('portal.plans.show', $other->id))->assertNotFound();
    }
}
