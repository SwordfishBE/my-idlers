<?php

namespace App\Console\Commands;

use App\Models\IPs;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class BackfillIpCoordinates extends Command
{
    protected $signature = 'ips:backfill-coordinates';

    protected $description = 'Backfill latitude and longitude for IPs that have been fetched but lack coordinates';

    public function handle(): int
    {
        Cache::forget('map_data');

        $ips = IPs::where(function ($query) {
            $query->whereNull('latitude')
                ->orWhereNull('longitude');
        })
            ->get();

        if ($ips->isEmpty()) {
            $this->info('No IPs need coordinate backfilling.');
            return Command::SUCCESS;
        }

        $this->info("Found {$ips->count()} IPs to backfill.");

        $bar = $this->output->createProgressBar($ips->count());
        $bar->start();

        $updated = 0;
        $failed = 0;

        foreach ($ips as $ip) {
            $result = IPs::getUpdateIpInfo($ip);

            if ($result) {
                $updated++;
            } else {
                $failed++;
                $this->newLine();
                $this->warn("Failed to fetch info for {$ip->address}");
            }

            $bar->advance();

            // Space requests to avoid overwhelming the lookup service.
            if (!$ips->last()->is($ip)) {
                sleep(1);
            }
        }

        $bar->finish();
        $this->newLine(2);
        Cache::forget('map_data');
        $this->info("Done. Updated: {$updated}, Failed: {$failed}");

        return Command::SUCCESS;
    }
}
