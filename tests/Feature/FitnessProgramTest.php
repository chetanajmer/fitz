<?php

namespace Tests\Feature;

use App\Enums\FitnessPlanType;
use App\Livewire\Gym\Content\Programs;
use App\Livewire\Gym\Members\MemberPrograms;
use App\Livewire\Portal\Dashboard;
use App\Models\FitnessPlan;
use App\Models\FitnessProgram;
use App\Models\FitnessProgramDay;
use App\Models\Gym;
use App\Models\MemberProgram;
use App\Models\ProgramDayLog;
use App\Models\User;
use App\Services\ProgramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class FitnessProgramTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-23 09:00')); // a Monday
        $this->owner = $this->createGymOwner('Program Gym');
        $this->gym = $this->gymOf($this->owner);
    }

    protected function importAll(): void
    {
        $this->actingAs($this->owner);
        $this->inGym($this->gym, function () {
            app(ProgramService::class)->importDefaults(FitnessPlanType::Workout);
            app(ProgramService::class)->importDefaults(FitnessPlanType::Diet);
        });
    }

    protected function program(string $key): FitnessProgram
    {
        return $this->inGym($this->gym, fn () => FitnessProgram::where('library_key', $key)->with('days.plan')->sole());
    }

    public function test_default_programs_import_with_their_plans_and_cover_every_goal_and_diet_preference(): void
    {
        $this->importAll();

        $this->inGym($this->gym, function () {
            $this->assertSame(12, FitnessProgram::ofType('workout')->count());
            $this->assertSame(17, FitnessProgram::ofType('diet')->count());
            $this->assertEqualsCanonicalizing(
                ['weight_loss', 'muscle_gain', 'lean', 'strength', 'general_fitness', 'endurance', 'flexibility', 'home', 'low_impact'],
                FitnessProgram::ofType('workout')->distinct()->pluck('goal')->map->value->all(),
            );
            foreach (['weight_loss', 'muscle_gain', 'lean'] as $goal) {
                $this->assertEqualsCanonicalizing(['veg', 'eggetarian', 'non_veg', 'vegan'],
                    FitnessProgram::ofType('diet')->where('goal', $goal)->distinct()->pluck('diet_preference')->map->value->all(), $goal);
            }

            // Every non-rest day points at an imported plan.
            $this->assertSame(0, FitnessProgramDay::where('is_rest', false)->whereNull('fitness_plan_id')->count());
            $this->assertTrue(FitnessPlan::where('library_key', 'diet-wl-vegan-1400')->exists());
        });

        // Importing again never duplicates.
        $again = $this->inGym($this->gym, fn () => app(ProgramService::class)->importDefaults(FitnessPlanType::Workout));
        $this->assertSame(0, $again['programs_created']);
        $this->assertSame(12, $again['programs_skipped']);
    }

    public function test_assigned_program_gives_the_right_plan_for_each_day(): void
    {
        $this->importAll();
        $member = $this->createMember($this->gym, ['fitness_goal' => 'Weight loss', 'diet_preference' => 'eggetarian']);
        $workout = $this->program('prog-wo-wl-beginner');
        $diet = $this->program('prog-diet-wl-egg');

        $service = app(ProgramService::class);
        $this->inGym($this->gym, function () use ($service, $member, $workout, $diet) {
            $assignment = $service->assign($member, $workout, '2026-11-23', $this->owner);
            $service->assign($member, $diet, '2026-11-23', $this->owner);

            $this->assertSame('2027-01-17', $assignment->end_date->toDateString(), '8 weeks');

            $day1 = $service->scheduleOn($member, Carbon::parse('2026-11-23'));
            $this->assertSame('Full body strength A', $day1['workout']['title']);
            $this->assertSame('Full Body A (Beginner)', $day1['workout']['plan']['title']);
            $this->assertNotEmpty($day1['workout']['plan']['sections']);
            $this->assertSame('1500 kcal Eggetarian Fat Loss', $day1['diet']['plan']['title']);

            $day4 = $service->scheduleOn($member, Carbon::parse('2026-11-26'));
            $this->assertTrue($day4['workout']['is_rest']);
            $this->assertNull($day4['workout']['plan']);

            // The 7-day cycle repeats: day 8 = day 1, week 2.
            $day8 = $service->scheduleOn($member, Carbon::parse('2026-11-30'));
            $this->assertSame('Full body strength A', $day8['workout']['title']);
            $this->assertSame(2, $day8['workout']['week']);

            // Outside the program: nothing.
            $this->assertNull($service->scheduleOn($member, Carbon::parse('2027-01-18'))['workout']);
        });
    }

    public function test_fourteen_day_cycle_and_reassigning_replaces_the_current_program(): void
    {
        $this->importAll();
        $member = $this->createMember($this->gym);
        $service = app(ProgramService::class);

        $this->inGym($this->gym, function () use ($service, $member) {
            $first = $service->assign($member, $this->program('prog-wo-strength-5x5'), '2026-11-23');
            $this->assertSame('Strength A', $service->scheduleOn($member, Carbon::parse('2026-11-23'))['workout']['title']);
            $this->assertSame('Strength B', $service->scheduleOn($member, Carbon::parse('2026-11-30'))['workout']['title'], 'week 2 starts with B');

            $service->assign($member, $this->program('prog-wo-mg-ppl'), '2026-11-24');
            $this->assertSame('stopped', $first->fresh()->status);
            $this->assertSame(1, MemberProgram::where('member_id', $member->id)->where('status', 'active')->count());
            $this->assertStringContainsString('Push', $service->scheduleOn($member, Carbon::parse('2026-11-24'))['workout']['title']);
        });
    }

    public function test_member_app_programs_and_schedule_api(): void
    {
        $this->importAll();
        $member = $this->createMember($this->gym);
        $this->inGym($this->gym, function () use ($member) {
            app(ProgramService::class)->assign($member, $this->program('prog-wo-mg-ppl'), '2026-11-23');
            app(ProgramService::class)->assign($member, $this->program('prog-diet-mg-vegan'), '2026-11-23');
        });

        $this->app['auth']->forgetGuards();
        $token = $member->createToken('app', ['member'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me/programs')->assertOk()
            ->assertJsonPath('data.workout_program.program.goal', 'muscle_gain')
            ->assertJsonPath('data.workout_program.current_day', 1)
            ->assertJsonPath('data.diet_program.program.diet_preference', 'vegan')
            ->assertJsonPath('data.today.workout.title', 'Push — chest, shoulders, triceps')
            ->assertJsonPath('data.today.diet.plan.title', '2500 kcal Vegan Muscle Gain')
            ->assertJsonCount(7, 'data.week')
            ->assertJsonPath('data.week.6.workout.is_rest', true)
            ->assertJsonStructure(['data' => ['today' => ['workout' => ['plan' => ['sections' => [['title', 'subtitle', 'body', 'image_url']]]]]]]);

        $this->withToken($token)->getJson('/api/v1/me/schedule?date=2026-11-25')->assertOk()
            ->assertJsonPath('data.date', '2026-11-25')
            ->assertJsonPath('data.workout.title', 'Legs')
            ->assertJsonPath('data.workout.cycle_day', 3);

        $this->withToken($token)->getJson('/api/v1/me/schedule?date=2030-01-01')->assertOk()
            ->assertJsonPath('data.workout', null)->assertJsonPath('data.diet', null);
    }

    public function test_member_portal_shows_assigned_programs_today_and_schedule(): void
    {
        $this->importAll();
        $member = $this->createMember($this->gym, ['email' => 'prog@example.test'], ['portal_password' => 'password']);
        $this->inGym($this->gym, function () use ($member) {
            app(ProgramService::class)->assign($member, $this->program('prog-wo-wl-beginner'), '2026-11-23');
            app(ProgramService::class)->assign($member, $this->program('prog-diet-wl-egg'), '2026-11-23');
        });

        $this->actingAs($member->user);
        $this->get(route('portal.dashboard'))->assertOk()
            ->assertSee('My workout program')->assertSee('My diet program')
            ->assertSee('Weight Loss')->assertSee('Full body strength A')
            ->assertSee('My schedule')->assertSee('Rest & recovery');

        $this->get(route('portal.plans', 'workout'))->assertOk()
            ->assertSee('My workout program')->assertDontSee('My diet program')
            ->assertSee('Full body strength A')->assertSee('Open full plan');

        $this->get(route('portal.plans', 'diet'))->assertOk()
            ->assertSee('My diet program')->assertSee('1500 kcal Eggetarian Fat Loss')->assertSee('Breakfast');
    }

    public function test_members_log_program_days_from_the_app_and_adherence_is_tracked(): void
    {
        $this->importAll();
        $member = $this->createMember($this->gym);
        $this->inGym($this->gym, fn () => app(ProgramService::class)->assign($member, $this->program('prog-wo-wl-beginner'), '2026-11-23'));
        $this->app['auth']->forgetGuards();
        $token = $member->createToken('app', ['member'])->plainTextToken;

        // Day 1 (Mon): done.
        $this->withToken($token)->postJson('/api/v1/me/schedule/log', ['type' => 'workout', 'status' => 'done', 'note' => 'Felt good'])
            ->assertCreated()->assertJsonPath('data.workout.status', 'done')->assertJsonPath('data.workout.log.note', 'Felt good');

        // Future days, rest days and other program types are rejected.
        $this->withToken($token)->postJson('/api/v1/me/schedule/log', ['type' => 'workout', 'status' => 'done', 'date' => '2026-11-24'])->assertUnprocessable();
        $this->withToken($token)->postJson('/api/v1/me/schedule/log', ['type' => 'diet', 'status' => 'done'])->assertUnprocessable()->assertJsonValidationErrors('type');

        // Wed: skipped. Tue: not logged. Thu: rest day.
        $this->travelTo(Carbon::parse('2026-11-26 09:00'));
        $this->withToken($token)->postJson('/api/v1/me/schedule/log', ['type' => 'workout', 'status' => 'skipped', 'date' => '2026-11-25'])->assertCreated();
        $this->withToken($token)->postJson('/api/v1/me/schedule/log', ['type' => 'workout', 'status' => 'done'])->assertUnprocessable();
        $this->withToken($token)->postJson('/api/v1/me/schedule/log', ['type' => 'workout', 'status' => 'done', 'date' => '2026-11-23'])
            ->assertUnprocessable()->assertJsonValidationErrors('date'); // more than 2 days back

        $this->withToken($token)->getJson('/api/v1/me/programs')->assertOk()
            ->assertJsonPath('data.workout_program.adherence.planned_days', 3)
            ->assertJsonPath('data.workout_program.adherence.done_days', 1)
            ->assertJsonPath('data.workout_program.adherence.skipped_days', 1)
            ->assertJsonPath('data.workout_program.adherence.missed_days', 1)
            ->assertJsonPath('data.today.workout.status', 'rest')
            ->assertJsonPath('data.week.1.workout.status', 'upcoming');

        $this->withToken($token)->getJson('/api/v1/me/schedule?date=2026-11-24')->assertJsonPath('data.workout.status', 'missed');

        // Undo.
        $this->withToken($token)->deleteJson('/api/v1/me/schedule/log', ['type' => 'workout', 'date' => '2026-11-25'])->assertOk()
            ->assertJsonPath('data.workout.status', 'missed');
        $this->assertSame(1, $this->inGym($this->gym, fn () => ProgramDayLog::count()));
    }

    public function test_member_marks_today_done_in_the_portal_and_staff_see_it(): void
    {
        $this->importAll();
        $member = $this->createMember($this->gym, ['name' => 'Portal Logger', 'email' => 'log@example.test'], ['portal_password' => 'password']);
        $this->inGym($this->gym, function () use ($member) {
            app(ProgramService::class)->assign($member, $this->program('prog-wo-wl-beginner'), '2026-11-23');
            app(ProgramService::class)->assign($member, $this->program('prog-diet-wl-egg'), '2026-11-23');
        });

        $this->actingAs($member->user);
        Livewire::test(Dashboard::class)
            ->assertSee('Workout done')
            ->call('logProgramDay', 'workout', 'done')->assertHasNoErrors()
            ->assertSee('workout done')
            ->call('logProgramDay', 'diet', 'skipped')->assertSee('Skipped today')
            ->call('undoProgramDay', 'diet')->assertSee('I followed my diet');

        $this->assertSame('done', $this->inGym($this->gym, fn () => ProgramDayLog::sole()->status));

        $this->get(route('portal.plans', 'workout'))->assertOk()->assertSee('Other workout plans');

        $this->actingAs($this->owner);
        Livewire::test(MemberPrograms::class, ['member' => $member])
            ->assertSee('Followed 1/1 days (100%)')->assertSee('Last 7 days')->assertSee('Done');
    }

    public function test_member_portal_without_programs_hides_the_section(): void
    {
        $member = $this->createMember($this->gym, ['email' => 'none@example.test'], ['portal_password' => 'password']);

        $this->actingAs($member->user);
        $this->get(route('portal.dashboard'))->assertOk()->assertDontSee('My schedule');
    }

    public function test_staff_assign_from_member_page_with_diet_preference_suggested(): void
    {
        $this->importAll();
        $member = $this->createMember($this->gym, ['fitness_goal' => 'Fat loss', 'diet_preference' => 'vegan']);
        $vegan = $this->program('prog-diet-wl-vegan');

        Livewire::test(MemberPrograms::class, ['member' => $member])
            ->call('openAssign', 'diet')
            ->assertSet('goal', 'weight_loss')
            ->assertSet('preference', 'vegan')
            ->assertSee('Weight Loss — Vegan')->assertDontSee('Weight Loss — Eggetarian')
            ->set('programId', (string) $vegan->id)
            ->call('assign')->assertHasNoErrors()
            ->assertSee('Weight Loss — Vegan')->assertSee('Next 7 days');

        $this->get(route('gym.members.show', [$member, 'tab' => 'plans']))->assertOk()->assertSee('Diet program');
    }

    public function test_programs_page_import_and_custom_program_editor(): void
    {
        $this->actingAs($this->owner);
        $plan = $this->inGym($this->gym, fn () => FitnessPlan::create(['type' => 'workout', 'title' => 'My Leg Day', 'is_published' => true]));

        Livewire::test(Programs::class)
            ->call('openImport')->assertSee('Weight Loss — Beginner')
            ->call('import')
            ->assertSee('Push / Pull / Legs')
            ->call('create')
            ->set('form.title', 'Owner Special')
            ->set('form.goal', 'strength')
            ->set('days', [['title' => 'Legs', 'fitness_plan_id' => (string) $plan->id, 'notes' => ''], ['title' => '', 'fitness_plan_id' => '', 'notes' => '']])
            ->call('save')->assertHasNoErrors()
            ->assertSee('Owner Special');

        $custom = $this->inGym($this->gym, fn () => FitnessProgram::where('title', 'Owner Special')->with('days')->sole());
        $this->assertSame(2, $custom->cycleLength());
        $this->assertSame($plan->id, $custom->days[0]->fitness_plan_id);
        $this->assertTrue($custom->days[1]->is_rest);

        $this->get(route('gym.programs', ['type' => 'diet']))->assertOk()->assertSee('Diet programs');
    }

    public function test_trainers_assign_only_to_their_members_and_cannot_edit_programs(): void
    {
        $this->importAll();
        $trainer = $this->createTrainer($this->gym);
        $mine = $this->createMember($this->gym, [], ['trainer_id' => $trainer->id]);
        $other = $this->createMember($this->gym);
        $program = $this->program('prog-wo-general-beginner');

        $this->actingAs($trainer);
        Livewire::test(MemberPrograms::class, ['member' => $mine])->call('openAssign', 'workout')
            ->set('programId', (string) $program->id)->call('assign')->assertHasNoErrors();
        Livewire::test(MemberPrograms::class, ['member' => $other])->assertForbidden();

        Livewire::test(Programs::class)->assertOk()->call('create')->assertForbidden();
        $this->assertSame(1, $this->inGym($this->gym, fn () => MemberProgram::count()));
    }

    public function test_programs_are_isolated_between_gyms(): void
    {
        $this->importAll();
        $otherGym = $this->gymOf($this->createGymOwner('Other'));
        $outsider = $this->createMember($otherGym);

        $this->expectException(ValidationException::class);
        $this->inGym($otherGym, fn () => app(ProgramService::class)->assign($outsider, $this->program('prog-wo-lean')));
    }
}
