<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Media\ImageOptimizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD with WebP support is required.');
        }

        Storage::fake('public');
        $this->actingAs(User::factory()->create());
    }

    public function test_a_large_photo_is_resized_and_stored_as_webp(): void
    {
        $original = $this->photo('jpg', 3600, 2400);

        $this->post('/vi/admin/media/upload', ['file' => $original, 'folder' => 'general'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $files = Storage::disk('public')->files('general');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.webp', $files[0]);

        [$width, $height] = getimagesize(Storage::disk('public')->path($files[0]));
        $this->assertSame(2560, $width);
        $this->assertSame(1707, $height);
        $this->assertLessThan($original->getSize(), Storage::disk('public')->size($files[0]));
    }

    public function test_a_small_photo_keeps_its_dimensions(): void
    {
        $this->post('/vi/admin/media/upload', ['file' => $this->photo('jpg', 800, 600), 'folder' => 'general'])
            ->assertRedirect();

        $file = Storage::disk('public')->files('general')[0];
        $this->assertStringEndsWith('.webp', $file);
        $this->assertSame([800, 600], array_slice(getimagesize(Storage::disk('public')->path($file)), 0, 2));
    }

    public function test_png_transparency_survives_the_conversion(): void
    {
        $image = imagecreatetruecolor(400, 300);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledrectangle($image, 100, 100, 300, 200, imagecolorallocate($image, 200, 30, 30));
        $path = tempnam(sys_get_temp_dir(), 'png');
        imagepng($image, $path, 0);

        $this->post('/vi/admin/media/upload', [
            'file' => new UploadedFile($path, 'logo.png', 'image/png', null, true),
            'folder' => 'general',
        ])->assertRedirect();

        $file = Storage::disk('public')->files('general')[0];
        $this->assertStringEndsWith('.webp', $file);
        $stored = imagecreatefromwebp(Storage::disk('public')->path($file));
        $this->assertSame(127, imagecolorat($stored, 5, 5) >> 24 & 0x7F);
        $this->assertSame(0, imagecolorat($stored, 200, 150) >> 24 & 0x7F);
    }

    public function test_settings_images_keep_their_format(): void
    {
        $this->post('/vi/admin/media/upload', ['file' => $this->photo('jpg', 3000, 1000), 'folder' => 'settings'])
            ->assertRedirect();

        $file = Storage::disk('public')->files('settings')[0];
        $this->assertStringEndsWith('.jpg', $file);
        $this->assertSame(2560, getimagesize(Storage::disk('public')->path($file))[0]);
    }

    public function test_gif_is_stored_untouched(): void
    {
        $gif = UploadedFile::fake()->image('animation.gif', 50, 50);
        $bytes = file_get_contents($gif->getRealPath());

        $this->post('/vi/admin/media/upload', ['file' => $gif, 'folder' => 'general'])->assertRedirect();

        $file = Storage::disk('public')->files('general')[0];
        $this->assertStringEndsWith('.gif', $file);
        $this->assertSame($bytes, Storage::disk('public')->get($file));
    }

    public function test_optimisation_can_be_switched_off(): void
    {
        config(['media.optimize.enabled' => false]);
        $original = $this->photo('jpg', 3000, 2000);
        $bytes = file_get_contents($original->getRealPath());

        $this->post('/vi/admin/media/upload', ['file' => $original, 'folder' => 'general'])->assertRedirect();

        $file = Storage::disk('public')->files('general')[0];
        $this->assertStringEndsWith('.jpg', $file);
        $this->assertSame($bytes, Storage::disk('public')->get($file));
    }

    public function test_a_file_that_is_not_an_image_is_left_alone(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bad');
        file_put_contents($path, 'not an image');

        $this->assertNull(app(ImageOptimizer::class)->optimize($path, 'jpg'));
    }

    private function photo(string $extension, int $width, int $height): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        mt_srand(7);
        for ($i = 0; $i < 400; $i++) {
            $color = imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            $x = mt_rand(0, $width);
            $y = mt_rand(0, $height);
            imagefilledrectangle($image, $x, $y, $x + mt_rand(20, 600), $y + mt_rand(20, 400), $color);
        }
        $path = tempnam(sys_get_temp_dir(), 'photo');
        imagejpeg($image, $path, 95);

        return new UploadedFile($path, "photo.{$extension}", 'image/jpeg', null, true);
    }
}
