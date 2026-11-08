<?php

namespace App\Console\Commands;

use App\Models\Listing;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('listings:prune {--days= : Aufbewahrung in Tagen (Default LISTING_RETENTION_DAYS)}')]
#[Description('Löscht Inserate älter als die Aufbewahrungsfrist inkl. Dateien')]
class ListingsPrune extends Command
{
    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('flipper.retention_days'));
        $count = 0;

        Listing::where('created_at', '<', now()->subDays($days))->each(function (Listing $listing) use (&$count) {
            $listing->deleteFiles();
            $listing->delete();
            $count++;
        });

        $this->info("{$count} Inserat(e) älter als {$days} Tage gelöscht.");

        return self::SUCCESS;
    }
}
