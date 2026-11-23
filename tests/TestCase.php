<?php

namespace Tests;

use App\Enums\PlanDuration;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\GymRegistrationService;
use App\Services\MemberService;
use App\Services\TrainerService;
use App\Tenancy\TenantManager;
use Database\Seeders\PlatformSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected bool $seedGymai = true;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->seedGymai && in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            $this->seed([RolesAndPermissionsSeeder::class, PlatformSeeder::class]);
        }
    }

    /** Register a gym through the real registration service; returns the owner (gym_admin). */
    protected function createGymOwner(string $gymName = 'Test Gym'): User
    {
        return app(GymRegistrationService::class)->register([
            'owner_name' => 'Owner '.Str::random(4),
            'email' => Str::lower(Str::random(8)).'@example.test',
            'phone' => '9000000000',
            'password' => 'password',
            'gym_name' => $gymName,
            'city' => 'Pune',
        ], login: false);
    }

    protected function gymOf(User $user): Gym
    {
        return Gym::findOrFail($user->gym_id);
    }

    /** Run a callback inside a gym's tenant context (as the CLI / services would). */
    protected function inGym(Gym $gym, \Closure $callback): mixed
    {
        return app(TenantManager::class)->runAs($gym, $callback);
    }

    protected function createPlan(Gym $gym, array $attributes = []): MembershipPlan
    {
        return $this->inGym($gym, fn () => MembershipPlan::create($attributes + [
            'name' => 'Monthly', 'duration_type' => PlanDuration::Monthly, 'duration_days' => 30, 'price' => 1000,
            'is_active' => true, 'is_public' => true,
        ]));
    }

    protected function createMember(Gym $gym, array $attributes = [], array $extra = []): Member
    {
        return $this->inGym($gym, fn () => app(MemberService::class)->create($attributes + [
            'name' => 'Member '.Str::random(4),
            'phone' => '9'.random_int(100000000, 999999999),
            'joining_date' => now()->toDateString(),
            'status' => 'active',
        ], $extra));
    }

    protected function createTrainer(Gym $gym): User
    {
        return $this->inGym($gym, fn () => app(TrainerService::class)->create([
            'name' => 'Trainer '.Str::random(4),
            'email' => Str::lower(Str::random(8)).'@trainer.test',
            'password' => 'password',
        ]));
    }
}
