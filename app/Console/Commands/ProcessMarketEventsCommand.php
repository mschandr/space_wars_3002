<?php

namespace App\Console\Commands;

use App\Services\MarketEventGenerator;
use App\Services\MarketEventService;
use Illuminate\Console\Command;

class ProcessMarketEventsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'market:process-events
                            {--generate : Force generate new events}
                            {--probability=0.15 : Probability of generating an event (0.0-1.0)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process market events (expire old, generate new)';

    private MarketEventService $eventService;
    private MarketEventGenerator $generator;

    public function __construct(MarketEventService $eventService, MarketEventGenerator $generator)
    {
        parent::__construct();
        $this->eventService = $eventService;
        $this->generator = $generator;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('════════════════════════════════════════════════════════════════');
        $this->info('  PROCESSING MARKET EVENTS');
        $this->info('════════════════════════════════════════════════════════════════');
        $this->newLine();

        // Step 1: Deactivate expired events
        $this->info('[1/2] Deactivating expired events...');
        $expiredCount = $this->eventService->deactivateExpiredEvents();
        if ($expiredCount > 0) {
            $this->info("  ✓ Deactivated {$expiredCount} expired event(s)");
        } else {
            $this->line('  No expired events found');
        }
        $this->newLine();

        // Step 2: Generate new events
        $this->info('[2/2] Generating new market events...');
        $probability = (float)$this->option('probability');

        if ($this->option('generate')) {
            // Force generate event
            $newEvent = $this->generator->generateRandomEvent(1.0);
            if ($newEvent) {
                $this->info("  ✓ Generated: {$newEvent->description}");
                $this->info("    Multiplier: {$newEvent->price_multiplier}x");
                $this->info("    Duration: {$newEvent->getDurationString()}");
            }
        } else {
            // Random chance to generate
            $newEvent = $this->generator->generateRandomEvent($probability);
            if ($newEvent) {
                $this->info("  ✓ New event generated!");
                $this->info("    {$newEvent->description}");
                $this->info("    Multiplier: {$newEvent->price_multiplier}x");
                $this->info("    Duration: {$newEvent->getDurationString()}");
            } else {
                $this->line("  No new events generated (probability: {$probability})");
            }
        }

        $this->newLine();
        $this->info('════════════════════════════════════════════════════════════════');
        $this->info('✓ Market events processed successfully');
        $this->info('════════════════════════════════════════════════════════════════');

        return Command::SUCCESS;
    }
}
