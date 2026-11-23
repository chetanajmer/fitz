<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use App\Services\MembershipService;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class TenancyIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_gym_admin_only_sees_their_own_members(): void
    {
        $ownerA = $this->createGymOwner('Gym A');
        $ownerB = $this->createGymOwner('Gym B');
        $memberA = $this->createMember($this->gymOf($ownerA), ['name' => 'Alice A']);
        $memberB = $this->createMember($this->gymOf($ownerB), ['name' => 'Bob B']);

        $this->actingAs($ownerA);

        $this->assertSame([$memberA->id], Member::pluck('id')->all());
        $this->assertNull(Member::find($memberB->id));
    }

    public function test_cross_tenant_urls_return_404(): void
    {
        $ownerA = $this->createGymOwner('Gym A');
        $ownerB = $this->createGymOwner('Gym B');
        $gymB = $this->gymOf($ownerB);
        $memberB = $this->createMember($gymB);
        $plan = $this->createPlan($gymB);
        $membership = $this->inGym($gymB, fn () => app(MembershipService::class)->assign($memberB, $plan, null, 0, null, ['amount' => 500, 'method' => 'cash']));
        $payment = $this->inGym($gymB, fn () => Payment::first());

        $this->actingAs($ownerA);

        $this->get(route('gym.members.show', $memberB->id))->assertNotFound();
        $this->get(route('gym.members.edit', $memberB->id))->assertNotFound();
        $this->get(route('gym.members.qr', $memberB->id))->assertNotFound();
        $this->get(route('gym.payments.receipt', $payment->id))->assertNotFound();
        $this->get(route('gym.memberships.invoice', $membership->id))->assertNotFound();
    }

    public function test_unauthenticated_queries_fail_closed(): void
    {
        $owner = $this->createGymOwner();
        $this->createMember($this->gymOf($owner));

        $this->assertSame(0, Member::count());
    }

    public function test_gym_id_is_stamped_from_tenant_and_cannot_be_spoofed(): void
    {
        $ownerA = $this->createGymOwner('Gym A');
        $ownerB = $this->createGymOwner('Gym B');

        $this->actingAs($ownerA);

        // Mass-assigned gym_id is ignored (guarded) and the tenant gym is used.
        $enquiry = Enquiry::create(['name' => 'Lead', 'phone' => '9999999999', 'gym_id' => $ownerB->gym_id]);
        $this->assertSame($ownerA->gym_id, $enquiry->gym_id);

        // Explicitly forcing another gym's id is rejected.
        $this->expectException(LogicException::class);
        $spoof = new Enquiry(['name' => 'Spoof', 'phone' => '8888888888']);
        $spoof->gym_id = $ownerB->gym_id;
        $spoof->save();
    }

    public function test_super_admin_sees_all_gyms_data(): void
    {
        $this->createMember($this->gymOf($this->createGymOwner('Gym A')));
        $this->createMember($this->gymOf($this->createGymOwner('Gym B')));

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $this->assertSame(2, Member::count());
        $this->assertFalse(app(TenantManager::class)->check());
    }
}
