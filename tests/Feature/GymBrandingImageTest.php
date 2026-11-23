<?php

namespace Tests\Feature;

use App\Livewire\Gym\Settings\Profile;
use App\Models\Gym;
use App\Models\User;
use App\Services\GymProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class GymBrandingImageTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->owner = $this->createGymOwner('Logo Gym');
        $this->gym = $this->gymOf($this->owner);
    }

    /** A large, detailed picture (gradients + noise) that compresses badly, like a real photo. */
    protected function photo(string $name, int $width, int $height, bool $transparent = false): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        if ($transparent) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }
        mt_srand(42);
        for ($y = 0; $y < $height; $y += 2) {
            for ($x = 0; $x < $width; $x += 2) {
                $alpha = $transparent && ($x < $width / 4 || $y < $height / 4) ? 127 : 0;
                $color = imagecolorallocatealpha($image,
                    (int) (($x / $width) * 200) + mt_rand(0, 55),
                    (int) (($y / $height) * 200) + mt_rand(0, 55),
                    mt_rand(0, 255), $alpha);
                imagefilledrectangle($image, $x, $y, $x + 1, $y + 1, $color);
            }
        }

        ob_start();
        $transparent ? imagepng($image, null, 0) : imagejpeg($image, null, 98);
        imagedestroy($image);

        return UploadedFile::fake()->createWithContent($name, (string) ob_get_clean());
    }

    public function test_uploaded_logo_and_banner_are_resized_and_compressed_to_their_budget(): void
    {
        $logo = $this->photo('logo.jpg', 2400, 2400);
        $banner = $this->photo('banner.jpg', 4000, 1500);
        $this->assertGreaterThan(1024 * 1024, $logo->getSize(), 'test logo should be over 1 MB');

        $this->actingAs($this->owner);
        Livewire::test(Profile::class)
            ->set('logo', $logo)->set('banner', $banner)
            ->call('save')->assertHasNoErrors();

        $gym = $this->gym->fresh();
        $disk = Storage::disk('public');

        $this->assertLessThanOrEqual(70 * 1024, $disk->size($gym->logo_path));
        [$w, $h] = getimagesizefromstring($disk->get($gym->logo_path));
        $this->assertLessThanOrEqual(512, max($w, $h));

        $this->assertLessThanOrEqual(500 * 1024, $disk->size($gym->banner_path));
        [$w, $h] = getimagesizefromstring($disk->get($gym->banner_path));
        $this->assertLessThanOrEqual(1920, $w);
        $this->assertLessThanOrEqual(720, $h);

        $this->assertStringStartsWith('gyms/'.$gym->id.'/branding/logo-', $gym->logo_path);
        $this->assertMatchesRegularExpression('/\.(webp|jpg|png)$/', $gym->logo_path);

        // Replacing the logo deletes the previous file.
        $old = $gym->logo_path;
        Livewire::test(Profile::class)->set('logo', $this->photo('new.jpg', 800, 800))->call('save')->assertHasNoErrors();
        $disk->assertMissing($old);
    }

    public function test_transparent_logos_keep_their_transparency(): void
    {
        $this->actingAs($this->owner);
        Livewire::test(Profile::class)->set('logo', $this->photo('logo.png', 1200, 1200, true))->call('save')->assertHasNoErrors();

        $gym = $this->gym->fresh();
        $this->assertLessThanOrEqual(70 * 1024, Storage::disk('public')->size($gym->logo_path));

        $image = imagecreatefromstring(Storage::disk('public')->get($gym->logo_path));
        $alpha = (imagecolorat($image, 2, 2) >> 24) & 0x7F;
        $this->assertGreaterThan(100, $alpha, 'the transparent corner stays transparent');
    }

    public function test_files_that_are_not_pictures_are_refused(): void
    {
        $this->actingAs($this->owner);
        Livewire::test(Profile::class)
            ->set('logo', UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf'))
            ->call('save')->assertHasErrors('logo');

        $this->assertNull($this->gym->fresh()->logo_path);
    }

    public function test_existing_large_logos_are_compressed_by_the_command(): void
    {
        $disk = Storage::disk('public');
        $big = $this->photo('old.jpg', 2400, 2400);
        $path = 'gyms/'.$this->gym->id.'/branding/old-logo.jpg';
        $disk->put($path, $big->getContent());
        $this->gym->forceFill(['logo_path' => $path])->save();

        $this->artisan('gymai:optimize-branding')->assertSuccessful();

        $gym = $this->gym->fresh();
        $this->assertNotSame($path, $gym->logo_path);
        $disk->assertMissing($path);
        $this->assertLessThanOrEqual(70 * 1024, $disk->size($gym->logo_path));

        // Already small: left alone.
        $this->artisan('gymai:optimize-branding')->assertSuccessful();
        $this->assertSame($gym->logo_path, $this->gym->fresh()->logo_path);
    }

    public function test_rules_are_the_requested_sizes(): void
    {
        $this->assertSame(70, GymProfileService::IMAGE_RULES['logo']['max_kb']);
        $this->assertSame(500, GymProfileService::IMAGE_RULES['banner']['max_kb']);
    }
}
