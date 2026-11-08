<?php

namespace App\Services\Images;

use Illuminate\Support\Facades\Process;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use RuntimeException;

/**
 * Bringt Uploads in eine einheitliche Form: JPEG, EXIF-Orientierung angewendet.
 * Die Pixel des Produkts werden nicht verändert (kein Schärfen, kein Aufhellen).
 */
class ExifNormalizer
{
    private ImageManager $manager;

    public function __construct()
    {
        // autoOrientation: dreht nach EXIF beim Dekodieren.
        $this->manager = ImageManager::usingDriver(GdDriver::class, autoOrientation: true);
    }

    /**
     * @return array{width: int, height: int}
     */
    public function normalize(string $sourcePath, string $targetJpegPath): array
    {
        $source = $sourcePath;
        $tmp = null;

        if ($this->isHeic($sourcePath)) {
            $tmp = tempnam(sys_get_temp_dir(), 'heic').'.jpg';
            $this->convertHeic($sourcePath, $tmp);
            $source = $tmp;
        }

        try {
            $image = $this->manager->decodePath($source);
            @mkdir(dirname($targetJpegPath), 0775, true);
            $image->encode(new JpegEncoder(quality: 95))->save($targetJpegPath);

            return ['width' => $image->width(), 'height' => $image->height()];
        } finally {
            if ($tmp && is_file($tmp)) {
                unlink($tmp);
            }
        }
    }

    /** Verkleinerte Kopie für die Vision-Eingabe (lange Kante max. 1568 px). */
    public function visionJpeg(string $path): string
    {
        return (string) $this->manager->decodePath($path)
            ->scaleDown(1568, 1568)
            ->encode(new JpegEncoder(quality: 85));
    }

    /** Fertiges JPG ohne Freistellen (Nahaufnahmen, fehlgeschlagene Freisteller): nur verkleinert. */
    public function resizedJpeg(string $path, string $target, int $longEdge = 1600): void
    {
        @mkdir(dirname($target), 0775, true);
        $this->manager->decodePath($path)
            ->scaleDown($longEdge, $longEdge)
            ->encode(new JpegEncoder(quality: 92))
            ->save($target);
    }

    public function isHeic(string $path): bool
    {
        $head = (string) file_get_contents($path, false, null, 0, 32);

        return str_contains(substr($head, 4, 8), 'ftyp')
            && preg_match('/ftyp(heic|heix|hevc|hevx|mif1|msf1)/', $head) === 1;
    }

    private function convertHeic(string $source, string $target): void
    {
        foreach ([['heif-convert', '-q', '95', $source, $target], ['magick', $source, '-auto-orient', $target], ['convert', $source, '-auto-orient', $target]] as $cmd) {
            if (! $this->binaryExists($cmd[0])) {
                continue;
            }
            $result = Process::timeout(60)->run($cmd);
            if ($result->successful() && is_file($target) && filesize($target) > 0) {
                return;
            }
        }

        throw new RuntimeException('HEIC-Foto konnte nicht umgewandelt werden. Bitte als JPEG hochladen (iPhone: Einstellungen › Kamera › Formate › Maximale Kompatibilität).');
    }

    private function binaryExists(string $bin): bool
    {
        return Process::run(['sh', '-c', 'command -v '.escapeshellarg($bin)])->successful();
    }
}
