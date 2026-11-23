<?php

namespace Tests\Feature;

use App\Enums\FitnessPlanType;
use App\Livewire\Platform\Library;
use App\Models\FitnessPlan;
use App\Models\Gym;
use App\Models\LibraryCategory;
use App\Models\LibraryPlan;
use App\Models\PlanCategory;
use App\Models\User;
use App\Services\FitnessLibraryImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class LibraryEditorTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');
        $this->gym = $this->gymOf($this->createGymOwner());
    }

    protected function pcos(): LibraryPlan
    {
        return LibraryPlan::where('key', 'diet-pcos-1500')->with('sections')->sole();
    }

    public function test_library_is_seeded_from_the_default_file(): void
    {
        $this->assertSame(12, LibraryPlan::where('type', 'diet')->count());
        $this->assertSame(17, LibraryPlan::where('type', 'workout')->count());
        $this->assertSame('Important', $this->pcos()->sections->first()->title);
    }

    public function test_super_admin_edits_text_and_photos_and_they_save(): void
    {
        $this->actingAs($this->admin);
        $plan = $this->pcos();

        $component = Livewire::test(Library::class)
            ->call('selectPlan', $plan->id)
            ->assertSet('form.title', '1500 kcal PCOS-friendly (Low-GI, High-Protein)');

        $breakfastKey = collect($component->get('sections'))->firstWhere('title', 'Breakfast')['key'];
        $breakfastIndex = collect($component->get('sections'))->search(fn ($s) => $s['title'] === 'Breakfast');

        $component
            ->set('form.title', 'PCOS 1500 — updated')
            ->set('form.daily_calories', '1450')
            ->set("sections.{$breakfastIndex}.body", 'Besan chilla with paneer')
            ->set('cover', UploadedFile::fake()->image('cover.jpg', 1600, 900))
            ->set("uploads.{$breakfastKey}", UploadedFile::fake()->image('breakfast.jpg'))
            ->call('addSection')
            ->set('sections.'.(count($plan->sections)).'.title', 'Post-dinner walk')
            ->call('savePlan')
            ->assertHasNoErrors();

        $plan = $this->pcos();
        $this->assertSame('PCOS 1500 — updated', $plan->title);
        $this->assertSame(1450, $plan->daily_calories);
        $this->assertSame('diet-pcos-1500', $plan->key, 'key never changes');
        Storage::disk('public')->assertExists($plan->cover_image_path);
        $breakfast = $plan->sections->firstWhere('title', 'Breakfast');
        $this->assertSame('Besan chilla with paneer', $breakfast->body);
        Storage::disk('public')->assertExists($breakfast->image_path);
        $this->assertSame('Post-dinner walk', $plan->sections->last()->title);

        // Reorder: photo stays with its section (no position-based mix-ups).
        $component = Livewire::test(Library::class)->call('selectPlan', $plan->id);
        $component->call('moveSection', $breakfastKeyAfter = collect($component->get('sections'))->firstWhere('title', 'Breakfast')['key'], -1)
            ->call('savePlan')->assertHasNoErrors();
        $this->assertSame($breakfast->image_path, $this->pcos()->sections->firstWhere('title', 'Breakfast')->image_path);

        // Remove a section → its photo file is deleted.
        $component = Livewire::test(Library::class)->call('selectPlan', $plan->id);
        $component->call('removeSection', collect($component->get('sections'))->firstWhere('title', 'Breakfast')['key'])->call('savePlan');
        Storage::disk('public')->assertMissing($breakfast->image_path);

        $this->get(route('platform.library', ['type' => 'workout']))->assertOk()->assertSee('Default library');
    }

    public function test_super_admin_creates_category_and_draft_plan_then_publishes(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Library::class)
            ->set('type', 'workout')
            ->call('editCategory')
            ->set('categoryForm.name', 'Yoga')
            ->set('categoryImage', UploadedFile::fake()->image('yoga.jpg'))
            ->call('saveCategory')
            ->assertHasNoErrors();

        $category = LibraryCategory::where('name', 'Yoga')->sole();
        $this->assertSame('workout-yoga', $category->key);
        Storage::disk('public')->assertExists($category->image_path);

        Livewire::test(Library::class)
            ->set('type', 'workout')
            ->call('newPlan', $category->id)
            ->assertSet('form.is_published', false)
            ->set('form.title', 'Morning Flow')
            ->set('sections.0.title', 'Sun salutation')
            ->set('sections.0.subtitle', '5 rounds')
            ->call('savePlan')
            ->assertHasNoErrors();

        $plan = LibraryPlan::where('title', 'Morning Flow')->sole();
        $this->assertFalse($plan->is_published);

        // Drafts are not offered to gyms.
        $keys = collect(app(FitnessLibraryImporter::class)->categories(FitnessPlanType::Workout))->pluck('key');
        $this->assertNotContains('workout-yoga', $keys);

        Livewire::test(Library::class)->set('type', 'workout')->call('selectPlan', $plan->id)
            ->set('form.is_published', true)->call('savePlan')->assertHasNoErrors();

        $this->actingAs($this->gym->owner);
        $result = app(FitnessLibraryImporter::class)->import(FitnessPlanType::Workout, ['workout-yoga']);
        $this->assertSame(1, $result['plans_created']);
        $this->assertSame(1, $result['images_added']);
        $this->assertSame('Sun salutation', FitnessPlan::where('library_key', $plan->key)->sole()->sections()->first()->title);
    }

    public function test_edits_flow_into_imports_and_photos_fill_earlier_imports(): void
    {
        // Gym imports the PCOS chart before any edits/photos.
        $this->actingAs($this->gym->owner);
        app(FitnessLibraryImporter::class)->import(FitnessPlanType::Diet, ['diet-pcos']);
        $gymPlan = FitnessPlan::where('library_key', 'diet-pcos-1500')->with('sections')->sole();
        $this->assertNull($gymPlan->cover_image_path);

        // Super Admin adds a cover + Breakfast photo and edits text.
        $this->actingAs($this->admin);
        $component = Livewire::test(Library::class)->call('selectPlan', $this->pcos()->id);
        $breakfastKey = collect($component->get('sections'))->firstWhere('title', 'Breakfast')['key'];
        $component->set('form.summary', 'Edited summary')
            ->set('cover', UploadedFile::fake()->image('c.jpg'))
            ->set("uploads.{$breakfastKey}", UploadedFile::fake()->image('b.jpg'))
            ->call('savePlan')->assertHasNoErrors();

        // Re-import: photos are added to the gym's copy; its text is not overwritten.
        $this->actingAs($this->gym->owner);
        $result = app(FitnessLibraryImporter::class)->import(FitnessPlanType::Diet, ['diet-pcos']);
        $this->assertSame(2, $result['images_added']);
        $gymPlan->refresh()->load('sections');
        $this->assertNotNull($gymPlan->cover_image_path);
        $this->assertStringStartsWith("gyms/{$this->gym->id}/", $gymPlan->cover_image_path);
        $this->assertNotNull($gymPlan->sections->firstWhere('title', 'Breakfast')->image_path);
        $this->assertNotSame('Edited summary', $gymPlan->summary, 'gym copies are never overwritten');

        // A brand-new gym gets the edited version.
        $newGym = $this->gymOf($this->createGymOwner('New'));
        $this->actingAs($newGym->owner);
        app(FitnessLibraryImporter::class)->import(FitnessPlanType::Diet, ['diet-pcos']);
        $this->assertSame('Edited summary', FitnessPlan::where('library_key', 'diet-pcos-1500')->sole()->summary);
    }

    public function test_delete_duplicate_reorder_and_access(): void
    {
        $this->actingAs($this->admin);
        $plan = $this->pcos();

        Livewire::test(Library::class)->call('selectPlan', $plan->id)->call('duplicatePlan');
        $copy = LibraryPlan::where('title', $plan->title.' (copy)')->sole();
        $this->assertFalse($copy->is_published);
        $this->assertNotSame($plan->key, $copy->key);
        $this->assertSame($plan->sections->count(), $copy->sections()->count());

        Livewire::test(Library::class)->call('selectPlan', $copy->id)->call('deletePlan');
        $this->assertNull(LibraryPlan::find($copy->id));

        $categories = LibraryCategory::where('type', 'diet')->orderBy('sort_order')->pluck('id');
        Livewire::test(Library::class)->call('moveCategory', $categories[1], -1);
        $this->assertSame($categories[1], LibraryCategory::where('type', 'diet')->orderBy('sort_order')->value('id'));

        $this->actingAs($this->gym->owner);
        $this->get(route('platform.library'))->assertForbidden();
        $this->assertSame(0, PlanCategory::count());
    }
}
