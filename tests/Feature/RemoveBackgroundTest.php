<?php

namespace Tests\Feature;

use App\Jobs\ComposeListingPhoto;
use App\Jobs\RemoveBackground;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Models\User;
use App\Services\Images\ExifNormalizer;
use App\Services\Rembg\RembgClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** rembg bekommt nie das volle Original: Alpha-Matting auf 12 MP hat den Docker-Host in den Swap getrieben. */
class RemoveBackgroundTest extends TestCase
{
    use RefreshDatabase;

    public function test_rembg_receives_downscaled_copy_and_temp_file_is_removed(): void
    {
        Storage::fake('local');
        Queue::fake([ComposeListingPhoto::class]);

        $listing = Listing::factory()->for(User::factory())->create();
        $relative = $listing->directory().'/originals/01.jpg';
        Storage::disk('local')->makeDirectory(dirname($relative));
        $img = imagecreatetruecolor(4000, 3000);
        imagejpeg($img, Storage::disk('local')->path($relative), 80);

        $photo = ListingPhoto::create([
            'listing_id' => $listing->id, 'position' => 1, 'kind' => 'overview',
            'original_path' => $relative, 'status' => 'uploaded', 'is_cover' => true,
        ]);

        $seen = new \ArrayObject;
        $this->app->instance(RembgClient::class, new class($seen) extends RembgClient
        {
            public function __construct(private \ArrayObject $seen)
            {
                parent::__construct('http://rembg.invalid', 'isnet-general-use', 5);
            }

            public function removeBackground(string $imagePath): string
            {
                $this->seen['path'] = $imagePath;
                $this->seen['size'] = getimagesize($imagePath);

                return file_get_contents(base_path('tests/Fixtures/luna/cutout.png'));
            }
        });

        (new RemoveBackground($photo->id))->handle(app(RembgClient::class), app(ExifNormalizer::class));

        [$w, $h] = $seen['size'];
        $this->assertSame(RemoveBackground::INPUT_EDGE, max($w, $h));
        $this->assertFileDoesNotExist($seen['path']);
        $this->assertSame('cut', $photo->fresh()->status);
        Queue::assertPushed(ComposeListingPhoto::class);
    }
}
