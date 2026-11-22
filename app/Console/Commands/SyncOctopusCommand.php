<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Sync\RunFullSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('octopus:sync')]
#[Description('Fetch account, consumption and tariff data from Octopus and price it locally')]
final class SyncOctopusCommand extends Command
{
    public function handle(RunFullSync $sync): int
    {
        if (blank(config('octopus.api_key')) || blank(config('octopus.account_number'))) {
            $this->error('OCTOPUS_API_KEY and OCTOPUS_ACCOUNT_NUMBER must both be set.');

            return self::FAILURE;
        }

        try {
            $this->info($sync->handle());
        } catch (Throwable $e) {
            $this->error('Sync failed: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
