<?php

namespace App\Jobs;

use App\Services\ListingFlow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Basis für KI-Jobs: 3 Versuche mit Backoff 30 s, 2 min, 5 min; danach failed + ntfy. */
abstract class ListingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public string $listingId) {}

    abstract protected function label(): string;

    public function failed(?Throwable $e): void
    {
        ListingFlow::fail($this->listingId, $this->label().' hat nicht geklappt: '.($e?->getMessage() ?? 'unbekannter Fehler'));
    }
}
