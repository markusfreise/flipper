<?php

namespace Tests\Unit;

use App\Services\Images\PhotoComposer;
use PHPUnit\Framework\TestCase;

class PhotoComposerTest extends TestCase
{
    public function test_output_is_1280x1600_with_product_centered_and_source_untouched(): void
    {
        $cutout = __DIR__.'/../Fixtures/luna/cutout.png';
        $before = md5_file($cutout);
        $out = sys_get_temp_dir().'/flohmarkt-compose-'.uniqid().'.jpg';

        (new PhotoComposer)->compose($cutout, $out);

        [$w, $h] = getimagesize($out);
        $this->assertSame([1280, 1600], [$w, $h]);
        $this->assertSame($before, md5_file($cutout), 'Freisteller darf nicht verändert werden');

        $img = imagecreatefromjpeg($out);
        $rgb = fn ($x, $y) => [(imagecolorat($img, $x, $y) >> 16) & 0xFF, (imagecolorat($img, $x, $y) >> 8) & 0xFF, imagecolorat($img, $x, $y) & 0xFF];

        // Verlauf: oben hell (≈246), unten dunkler (≈214), Ränder ohne Schatten
        $this->assertEqualsWithDelta(246, $rgb(10, 2)[0], 3);
        $this->assertEqualsWithDelta(214, $rgb(10, 1597)[0], 3);

        // Produktfarbe (40, 90, 170) in der Mitte, Farbe unverändert
        [$r, $g, $b] = $rgb(640, 1100);
        $this->assertEqualsWithDelta(40, $r, 6);
        $this->assertEqualsWithDelta(90, $g, 6);
        $this->assertEqualsWithDelta(170, $b, 6);

        // Kontaktschatten unter dem Produkt dunkler als der Hintergrund daneben
        $this->assertLessThan($rgb(40, 1500)[0] - 20, $rgb(640, 1500)[0]);

        unlink($out);
    }
}
