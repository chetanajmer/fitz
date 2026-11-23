<?php

namespace Tests\Feature;

use App\Livewire\Gym\Members\Import;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class MemberImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_import_creates_members_and_reports_skipped_rows(): void
    {
        $owner = $this->createGymOwner();
        $this->createMember($this->gymOf($owner), ['phone' => '9000000003']);
        $this->actingAs($owner);

        $csv = "Name,Phone,Email,Gender,Date of Birth,Joining Date\n"
            ."Asha Rao,9000000001,asha@example.test,F,1995-02-10,2026-01-05\n"
            ."Vikram Shah,9000000002,,male,10/03/1990,\n"
            ."Duplicate Person,9000000003,,,,\n"
            .",9000000004,,,,\n";

        $file = UploadedFile::fake()->createWithContent('members.csv', $csv);

        Livewire::test(Import::class)
            ->set('file', $file)
            ->call('import')
            ->assertHasNoErrors()
            ->assertSet('result.imported', 2)
            ->assertSet('result.skipped', 2);

        $asha = Member::where('phone', '9000000001')->sole();
        $this->assertSame('female', $asha->gender);
        $this->assertSame('2026-01-05', $asha->joining_date->toDateString());
        $this->assertSame('1990-03-10', Member::where('phone', '9000000002')->sole()->date_of_birth->toDateString());
        $this->assertSame(3, Member::count());
    }
}
