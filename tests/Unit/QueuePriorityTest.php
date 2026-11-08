<?php

namespace Tests\Unit;

use App\Jobs\ComposeListingPhoto;
use App\Jobs\IdentifyProduct;
use App\Jobs\RemoveBackground;
use App\Jobs\ResearchProduct;
use App\Jobs\WriteListingText;
use PHPUnit\Framework\TestCase;

/**
 * Bilder laufen in einer eigenen Queue, damit Recherche und Text nicht hinter
 * acht Freistell-Jobs warten. Der Worker läuft mit --queue=default,images.
 */
class QueuePriorityTest extends TestCase
{
    public function test_image_jobs_use_the_images_queue(): void
    {
        $this->assertSame('images', (new RemoveBackground('p'))->queue);
        $this->assertSame('images', (new ComposeListingPhoto('p'))->queue);
    }

    public function test_ai_jobs_stay_on_the_default_queue(): void
    {
        $this->assertNull((new IdentifyProduct('l'))->queue);
        $this->assertNull((new ResearchProduct('l'))->queue);
        $this->assertNull((new WriteListingText('l'))->queue);
    }

    public function test_production_worker_processes_default_before_images(): void
    {
        $unit = file_get_contents(__DIR__.'/../../_bootstrap/templates/flipper-queue.service');
        $this->assertStringContainsString('--queue=default,images', $unit);
    }
}
