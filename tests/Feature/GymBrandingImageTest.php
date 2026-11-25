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
        $transparent ? imagepng($image, null, 0) : imagejpeg($image, null, 90);
        imagedestroy($image);

        return UploadedFile::fake()->createWithContent($name, (string) ob_get_clean());
    }

    public function test_uploaded_logo_and_banner_are_resized_and_compressed_to_their_budget(): void
    {
        $logo = $this->photo('logo.jpg', 2000, 2000);
        $banner = $this->photo('banner.jpg', 3200, 1200);
        // Big enough to need real compression, small enough for the 10 MB upload limit on any OS
        // (JPEG encoders differ: the same picture is larger on Linux than on Windows).
        $this->assertGreaterThan(1024 * 1024, $logo->getSize(), 'test logo should be over 1 MB');
        $this->assertLessThan(10 * 1024 * 1024, max($logo->getSize(), $banner->getSize()), 'test pictures must stay under the upload limit');

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
        $big = $this->photo('old.jpg', 2000, 2000);
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

    public function test_very_large_photos_are_processed_within_a_128_mb_memory_limit(): void
    {
        // A 22-megapixel photo (5400x4080) needs ~88 MB once decoded. Run in a separate PHP process
        // limited to 128 MB, like a default PHP install, so the test process itself is not affected.
        $dir = sys_get_temp_dir();
        $big = $dir.'/gymai-big-'.uniqid().'.jpg';
        $script = $dir.'/gymai-opt-'.uniqid().'.php';
        // Scripts go in files: Windows command lines mangle % and quotes.
        $maker = $dir.'/gymai-make-'.uniqid().'.php';
        file_put_contents($maker, '<?php $w = 5400; $h = 4080; $i = imagecreatetruecolor($w, $h);'
            .'for ($y = 0; $y < $h; $y += 8) { imagefilledrectangle($i, 0, $y, $w, $y + 7, imagecolorallocate($i, ($y * 7) % 255, ($y * 3) % 255, ($y * 11) % 255)); }'
            .'imagejpeg($i, $argv[1], 90); imagepng($i, $argv[2], 6);');
        $bigPng = $dir.'/gymai-big-'.uniqid().'.png';
        exec(escapeshellarg(PHP_BINARY).' -d memory_limit=1G '.escapeshellarg($maker).' '.escapeshellarg($big).' '.escapeshellarg($bigPng));
        @unlink($maker);
        $this->assertFileExists($big);

        file_put_contents($script, '<?php require '.var_export(base_path('vendor/autoload.php'), true).';'
            .'$r = (new App\Support\ImageOptimizer)->optimize($argv[1], 1920, 720, 500 * 1024);'
            .'echo json_encode(["bytes" => $r["bytes"], "width" => $r["width"], "height" => $r["height"]]);');

        // JPEG and PNG (PNG decoding needs about twice the memory).
        foreach ([$big, $bigPng] as $file) {
            $output = [];
            exec(escapeshellarg(PHP_BINARY).' -d memory_limit=128M '.escapeshellarg($script).' '.escapeshellarg($file).' 2>&1', $output, $exit);
            @unlink($file);

            $this->assertSame(0, $exit, basename($file).': '.implode("\n", $output));
            $result = json_decode(end($output), true);
            $this->assertLessThanOrEqual(500 * 1024, $result['bytes']);
            $this->assertSame(720, $result['height']);
        }
        @unlink($script);
    }

    public function test_rules_are_the_requested_sizes(): void
    {
        $this->assertSame(70, GymProfileService::IMAGE_RULES['logo']['max_kb']);
        $this->assertSame(500, GymProfileService::IMAGE_RULES['banner']['max_kb']);
    }
}
