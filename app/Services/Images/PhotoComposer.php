<?php

namespace App\Services\Images;

use GdImage;
use RuntimeException;

/**
 * Port von listing_photos.py (Skill /verkaufen): Freisteller unverändert auf einen
 * neutralen hellgrauen Studioverlauf (246 → 214) mit weichem Kontaktschatten, 4:5.
 * Das Produkt wird nur skaliert, nie retuschiert.
 */
class PhotoComposer
{
    public const WIDTH = 1280;

    public const HEIGHT = 1600;

    private const FILL = 0.78;

    private const TOP = [246, 246, 246];

    private const BOTTOM = [214, 214, 214];

    private const SHADOW_OPACITY = 0.45;

    public function compose(string $cutoutPng, string $targetJpg): void
    {
        $cut = @imagecreatefrompng($cutoutPng);
        if (! $cut instanceof GdImage) {
            throw new RuntimeException('Freisteller nicht lesbar.');
        }
        imagesavealpha($cut, true);

        $cut = $this->trimToAlpha($cut);
        [$cw, $ch] = [self::WIDTH, self::HEIGHT];

        $scale = min($cw * self::FILL / imagesx($cut), $ch * self::FILL / imagesy($cut));
        $w = max(1, (int) round(imagesx($cut) * $scale));
        $h = max(1, (int) round(imagesy($cut) * $scale));
        $product = $this->transparentCanvas($w, $h);
        imagecopyresampled($product, $cut, 0, 0, 0, 0, $w, $h, imagesx($cut), imagesy($cut));

        $x = intdiv($cw - $w, 2);
        $y = intdiv($ch - $h, 2) + (int) ($ch * 0.03);

        $bg = $this->gradient($cw, $ch);
        $this->applyShadow($bg, $product, $x, $y);

        imagealphablending($bg, true);
        imagecopy($bg, $product, $x, $y, 0, 0, $w, $h);

        @mkdir(dirname($targetJpg), 0775, true);
        if (! imagejpeg($bg, $targetJpg, 92)) {
            throw new RuntimeException('JPG konnte nicht geschrieben werden.');
        }
    }

    private function gradient(int $w, int $h): GdImage
    {
        $bg = imagecreatetruecolor($w, $h);
        for ($y = 0; $y < $h; $y++) {
            $t = $y / max($h - 1, 1);
            $c = array_map(fn ($i) => (int) (self::TOP[$i] + (self::BOTTOM[$i] - self::TOP[$i]) * $t), [0, 1, 2]);
            imageline($bg, 0, $y, $w - 1, $y, imagecolorallocate($bg, ...$c));
        }

        return $bg;
    }

    /**
     * Kontaktschatten wie im Python-Original: Alpha des Produkts × 0,45, auf 102 % Breite und
     * 25 % Höhe gestaucht, unten angesetzt, GaussianBlur(28). Gerechnet auf 1/4 Auflösung mit
     * drei Box-Blur-Durchgängen (≈ Gauß), dann bilinear hochskaliert und multipliziert.
     */
    private function applyShadow(GdImage $bg, GdImage $product, int $x, int $y): void
    {
        $f = 4;
        [$cw, $ch] = [imagesx($bg), imagesy($bg)];
        [$w, $h] = [imagesx($product), imagesy($product)];
        $sw = max(1, (int) ($w * 1.02));
        $sh = max(1, (int) ($h * 0.25));
        $W = intdiv($cw, $f);
        $H = intdiv($ch, $f);

        $squashed = $this->transparentCanvas(max(1, intdiv($sw, $f)), max(1, intdiv($sh, $f)));
        imagecopyresampled($squashed, $product, 0, 0, 0, 0, imagesx($squashed), imagesy($squashed), $w, $h);

        $a = array_fill(0, $W * $H, 0.0);
        $ox = intdiv($x - (int) ($w * 0.01), $f);
        $oy = intdiv($y + $h - intdiv($sh, 2), $f);
        for ($j = 0, $sh2 = imagesy($squashed); $j < $sh2; $j++) {
            $ty = $oy + $j;
            if ($ty < 0 || $ty >= $H) {
                continue;
            }
            for ($i = 0, $sw2 = imagesx($squashed); $i < $sw2; $i++) {
                $tx = $ox + $i;
                if ($tx < 0 || $tx >= $W) {
                    continue;
                }
                $alpha = 1 - ((imagecolorat($squashed, $i, $j) >> 24) & 0x7F) / 127;
                $a[$ty * $W + $tx] = $alpha * self::SHADOW_OPACITY;
            }
        }

        $r = (int) round(28 / $f);
        for ($pass = 0; $pass < 3; $pass++) {
            $a = $this->boxBlur($a, $W, $H, $r, true);
            $a = $this->boxBlur($a, $W, $H, $r, false);
        }

        $small = imagecreatetruecolor($W, $H);
        $rowMin = $H;
        for ($j = 0; $j < $H; $j++) {
            for ($i = 0; $i < $W; $i++) {
                $v = (int) round(255 * (1 - min(1, $a[$j * $W + $i])));
                if ($v < 255) {
                    $rowMin = min($rowMin, $j);
                }
                imagesetpixel($small, $i, $j, ($v << 16) | ($v << 8) | $v);
            }
        }
        if ($rowMin >= $H) {
            return;
        }

        $mask = imagecreatetruecolor($cw, $ch);
        imagecopyresampled($mask, $small, 0, 0, 0, 0, $cw, $ch, $W, $H);

        for ($py = max(0, ($rowMin - 1) * $f); $py < $ch; $py++) {
            for ($px = 0; $px < $cw; $px++) {
                $m = imagecolorat($mask, $px, $py) & 0xFF;
                if ($m >= 255) {
                    continue;
                }
                $c = imagecolorat($bg, $px, $py);
                $k = $m / 255;
                imagesetpixel($bg, $px, $py,
                    ((int) ((($c >> 16) & 0xFF) * $k) << 16) | ((int) ((($c >> 8) & 0xFF) * $k) << 8) | (int) (($c & 0xFF) * $k));
            }
        }
    }

    /** Ein Box-Blur-Durchgang (laufende Summe), horizontal oder vertikal. @param list<float> $a */
    private function boxBlur(array $a, int $W, int $H, int $r, bool $horizontal): array
    {
        $out = $a;
        $n = $horizontal ? $W : $H;
        $lines = $horizontal ? $H : $W;
        $div = 2 * $r + 1;
        for ($l = 0; $l < $lines; $l++) {
            $idx = $horizontal ? fn ($k) => $l * $W + $k : fn ($k) => $k * $W + $l;
            $sum = 0.0;
            for ($k = -$r; $k <= $r; $k++) {
                $sum += ($k >= 0 && $k < $n) ? $a[$idx($k)] : 0.0;
            }
            for ($k = 0; $k < $n; $k++) {
                $out[$idx($k)] = $sum / $div;
                $add = $k + $r + 1;
                $rem = $k - $r;
                $sum += ($add < $n ? $a[$idx($add)] : 0.0) - ($rem >= 0 ? $a[$idx($rem)] : 0.0);
            }
        }

        return $out;
    }

    /** Schneidet auf die Bounding-Box des Alphakanals (wie PIL getbbox). */
    private function trimToAlpha(GdImage $img): GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $step = max(1, intdiv(max($w, $h), 800)); // grob scannen, dann mit Rand
        [$minX, $minY, $maxX, $maxY] = [$w, $h, -1, -1];
        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) < 120) {
                    $minX = min($minX, $x);
                    $maxX = max($maxX, $x);
                    $minY = min($minY, $y);
                    $maxY = max($maxY, $y);
                }
            }
        }
        if ($maxX < 0) {
            throw new RuntimeException('Freisteller ist leer.');
        }
        $minX = max(0, $minX - $step);
        $minY = max(0, $minY - $step);
        $maxX = min($w - 1, $maxX + $step);
        $maxY = min($h - 1, $maxY + $step);

        $cropped = imagecrop($img, ['x' => $minX, 'y' => $minY, 'width' => $maxX - $minX + 1, 'height' => $maxY - $minY + 1]);
        imagesavealpha($cropped, true);

        return $cropped;
    }

    private function transparentCanvas(int $w, int $h): GdImage
    {
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));

        return $img;
    }
}
