<?php

namespace App\Console\Commands;

use App\Services\Contracts\ContractExpiryService;
use Illuminate\Console\Command;

class ContractExpiryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contracts:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process contract expirations and failures (hourly scheduled job)';

    /**
     * Execute the console command.
     */
    public function handle(ContractExpiryService $expiryService): int
    {
        $this->info('Processing contract expirations and failures...');

        $results = $expiryService->processExpirations();

        $this->info("✓ {$results['expired']} contracts expired");
        $this->info("✓ {$results['failed']} contracts failed (deadline exceeded)");

        return Command::SUCCESS;
    }
}
