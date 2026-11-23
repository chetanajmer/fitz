<?php

namespace App\Services;

use App\Enums\EnquirySource;
use App\Enums\EnquiryStatus;
use App\Models\Enquiry;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnquiryService
{
    public function __construct(
        protected TenantManager $tenant,
        protected MemberService $members,
        protected MembershipService $memberships,
    ) {}

    /** Create an enquiry inside the current tenant (staff-entered). */
    public function create(array $data): Enquiry
    {
        return DB::transaction(function () use ($data) {
            $enquiry = new Enquiry($data);
            $enquiry->interested_plan_id = $this->validPlanId($data['interested_plan_id'] ?? null);
            $enquiry->source = $data['source'] ?? EnquirySource::WalkIn->value;
            $enquiry->status = EnquiryStatus::New;
            $enquiry->save();

            $this->log($enquiry, 'created', null, EnquiryStatus::New->value, 'Enquiry created ('.$enquiry->source->label().').');

            return $enquiry;
        });
    }

    /**
     * Public QR / profile registration. Always creates an Enquiry — never a member.
     * The gym comes from the server-resolved join token, not from the form.
     */
    public function createFromPublic(Gym $gym, array $data, EnquirySource $source = EnquirySource::Qr): Enquiry
    {
        return $this->tenant->runAs($gym, fn () => $this->create(['source' => $source->value] + $data));
    }

    public function update(Enquiry $enquiry, array $data): Enquiry
    {
        $enquiry->fill($data);
        $enquiry->interested_plan_id = $this->validPlanId($data['interested_plan_id'] ?? null);
        $enquiry->save();

        return $enquiry;
    }

    public function changeStatus(Enquiry $enquiry, EnquiryStatus $status, ?string $note = null, ?string $followUpDate = null): Enquiry
    {
        if ($enquiry->isConverted()) {
            throw ValidationException::withMessages(['status' => 'Converted enquiries cannot change status.']);
        }

        if ($status === EnquiryStatus::Converted) {
            throw ValidationException::withMessages(['status' => 'Use "Convert to member" to convert an enquiry.']);
        }

        return DB::transaction(function () use ($enquiry, $status, $note, $followUpDate) {
            $from = $enquiry->status->value;
            $enquiry->status = $status;

            if ($followUpDate !== null) {
                $enquiry->follow_up_date = $followUpDate ?: null;
            }

            $enquiry->save();

            if ($from !== $status->value || $note || $followUpDate) {
                $this->log($enquiry, 'status_changed', $from, $status->value, $note);
            }

            return $enquiry;
        });
    }

    public function addNote(Enquiry $enquiry, string $note): void
    {
        $this->log($enquiry, 'note', null, null, $note);
    }

    /**
     * Convert an enquiry into a member. Idempotent-safe: the enquiry row is locked and
     * re-checked, and enquiries.converted_member_id / members.enquiry_id are unique.
     *
     * @param  array  $memberData  member fillable attributes
     * @param  array{trainer_id?:?int, plan_id?:?int, start_date?:?string, discount?:float, payment?:?array}  $options
     */
    public function convert(Enquiry $enquiry, array $memberData, array $options = []): Member
    {
        return DB::transaction(function () use ($enquiry, $memberData, $options) {
            /** @var Enquiry $locked */
            $locked = Enquiry::whereKey($enquiry->id)->lockForUpdate()->firstOrFail();

            if ($locked->isConverted()) {
                throw ValidationException::withMessages(['enquiry' => 'This enquiry has already been converted to a member.']);
            }

            $member = $this->members->create(
                $memberData + ['name' => $locked->name, 'phone' => $locked->phone, 'email' => $locked->email, 'fitness_goal' => $locked->fitness_goal],
                ['trainer_id' => $options['trainer_id'] ?? null, 'enquiry_id' => $locked->id, 'portal_password' => $options['portal_password'] ?? null],
            );

            if (! empty($options['plan_id'])) {
                $plan = MembershipPlan::findOrFail($options['plan_id']);
                $this->memberships->assign(
                    $member,
                    $plan,
                    $options['start_date'] ?? null,
                    (float) ($options['discount'] ?? 0),
                    null,
                    $options['payment'] ?? null,
                );
            }

            $from = $locked->status->value;
            $locked->status = EnquiryStatus::Converted;
            $locked->converted_member_id = $member->id;
            $locked->converted_at = now();
            $locked->converted_by = Auth::id();
            $locked->save();

            $this->log($locked, 'converted', $from, EnquiryStatus::Converted->value, "Converted to member {$member->member_code}.");

            return $member;
        });
    }

    protected function validPlanId(mixed $planId): ?int
    {
        if (! $planId) {
            return null;
        }

        // Global scope guarantees the plan belongs to the current gym.
        return MembershipPlan::whereKey($planId)->exists() ? (int) $planId : null;
    }

    protected function log(Enquiry $enquiry, string $type, ?string $from, ?string $to, ?string $note): void
    {
        $enquiry->activities()->create([
            'user_id' => Auth::id(),
            'type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
        ]);
    }
}
