<?php

namespace Tests\Feature;

use App\Enums\FitnessPlanType;
use App\Livewire\Gym\Content\Library;
use App\Models\FitnessPlan;
use App\Models\Gym;
use App\Models\PlanCategory;
use App\Services\FitnessLibraryImporter;
use App\Services\FitnessPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FitnessLibraryImportTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = $this->gymOf($this->createGymOwner());
    }

    protected function libraryCounts(string $type): array
    {
        $categories = app(FitnessLibraryImporter::class)->categories(FitnessPlanType::from($type));

        return [count($categories), array_sum(array_map(fn ($c) => count($c['plans']), $categories))];
    }

    public function test_library_data_is_well_formed(): void
    {
        $importer = app(FitnessLibraryImporter::class);
        $keys = [];

        foreach (FitnessPlanType::cases() as $type) {
            $this->assertNotEmpty($importer->categories($type), $type->value);
            foreach ($importer->categories($type) as $category) {
                $keys[] = $category['key'];
                $this->assertNotEmpty($category['plans'], $category['key']);
                foreach ($category['plans'] as $plan) {
                    $keys[] = $plan['key'];
                    $this->assertNotEmpty($plan['title']);
                    $this->assertNotEmpty($plan['sections'], $plan['key']);
                    foreach ($plan['sections'] as $section) {
                        $this->assertIsString($section['title'], $plan['key']);
                        $this->assertLessThanOrEqual(150, mb_strlen($section['title']), 'section title too long');
                        $this->assertLessThanOrEqual(150, mb_strlen((string) $section['subtitle']), 'subtitle too long in '.$plan['key']);
                    }
                }
            }
        }

        $this->assertSame(count($keys), count(array_unique($keys)), 'library keys must be unique');
    }

    public function test_admin_imports_all_diet_charts_once_and_reimport_never_duplicates(): void
    {
        // The gym already has its own "weight loss" category — it must be reused, not duplicated.
        $own = $this->inGym($this->gym, fn () => PlanCategory::create(['type' => 'diet', 'name' => 'weight loss']));
        [$categoryCount, $planCount] = $this->libraryCounts('diet');

        $this->actingAs($this->gym->owner);

        Livewire::test(Library::class, ['type' => 'diet'])
            ->call('openImport')
            ->assertSet('showImport', true)
            ->assertSee('Weight Loss')
            ->call('runImport')
            ->assertHasNoErrors()
            ->assertSet('showImport', false);

        $this->assertSame($categoryCount, PlanCategory::ofType('diet')->count());
        $this->assertSame($planCount, FitnessPlan::ofType('diet')->count());
        $this->assertSame('diet-weight-loss', $own->fresh()->library_key);
        $this->assertSame(6, FitnessPlan::where('plan_category_id', $own->id)->count());
        $this->assertTrue(FitnessPlan::ofType('diet')->get()->every(fn ($p) => $p->is_published && $p->member_id === null));
        $this->assertSame(0, FitnessPlan::ofType('workout')->count(), 'only the chosen type is imported');

        $plan = FitnessPlan::where('library_key', 'diet-wl-veg-1500')->with('sections')->sole();
        $this->assertSame(1500, $plan->daily_calories);
        $this->assertSame('Breakfast', $plan->sections[1]->title);

        // Second import: nothing new.
        $result = app(FitnessLibraryImporter::class)->import(FitnessPlanType::Diet);
        $this->assertSame(0, $result['plans_created']);
        $this->assertSame($planCount, $result['plans_skipped']);
        $this->assertSame($planCount, FitnessPlan::ofType('diet')->count());
    }

    public function test_selected_categories_as_drafts_and_deleted_plans_can_be_restored(): void
    {
        $this->actingAs($this->gym->owner);

        Livewire::test(Library::class, ['type' => 'workout'])
            ->call('openImport')
            ->set('importKeys', ['wo-ppl', 'not-a-real-key'])
            ->set('importPublish', false)
            ->call('runImport')
            ->assertHasNoErrors();

        $this->assertSame(3, FitnessPlan::ofType('workout')->count());
        $this->assertSame(0, FitnessPlan::ofType('workout')->published()->count());

        // Admin deletes one imported plan; importing again brings it back.
        app(FitnessPlanService::class)->deletePlan(FitnessPlan::where('library_key', 'wo-ppl-legs')->sole());
        $this->assertSame(2, FitnessPlan::ofType('workout')->count());
        $result = app(FitnessLibraryImporter::class)->import(FitnessPlanType::Workout, ['wo-ppl']);
        $this->assertSame(1, $result['plans_created']);

        Livewire::test(Library::class, ['type' => 'workout'])
            ->call('openImport')
            ->set('importKeys', [])
            ->call('runImport')
            ->assertHasErrors('importKeys');
    }

    public function test_copies_sent_to_members_do_not_count_as_imported(): void
    {
        $this->actingAs($this->gym->owner);
        app(FitnessLibraryImporter::class)->import(FitnessPlanType::Diet, ['diet-pcos']);
        $default = FitnessPlan::where('library_key', 'diet-pcos-1500')->sole();

        $copy = app(FitnessPlanService::class)->copyForMember($default, $this->createMember($this->gym));
        $this->assertNull($copy->library_key);

        $preview = collect(app(FitnessLibraryImporter::class)->preview(FitnessPlanType::Diet))->firstWhere('key', 'diet-pcos');
        $this->assertSame(1, $preview['imported']);
    }

    public function test_only_gym_admin_can_import_and_imports_stay_in_their_gym(): void
    {
        $trainer = $this->createTrainer($this->gym);
        $this->actingAs($trainer);
        $this->get(route('gym.content.index', 'diet'))->assertOk()->assertDontSee('Import default library');
        Livewire::test(Library::class, ['type' => 'diet'])->call('openImport')->assertForbidden();
        Livewire::test(Library::class, ['type' => 'diet'])->call('runImport')->assertForbidden();

        $this->actingAs($this->gym->owner);
        $this->get(route('gym.content.index', 'diet'))->assertOk()->assertSee('Import default library');
        app(FitnessLibraryImporter::class)->import(FitnessPlanType::Diet);

        $otherGym = $this->gymOf($this->createGymOwner('Other'));
        $this->assertSame(0, FitnessPlan::forGym($otherGym)->count());
    }
}
