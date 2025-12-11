<?php

namespace App\Console\Commands;

use App\Models\RolePromotion;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DisableExpiredPromotions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:disable-expired-promotions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Disable role promotions that have passed their end date';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = Carbon::now();

        // Find all active promotions that have passed their end date
        $expiredPromotions = RolePromotion::where('is_active', true)
            ->whereNotNull('end_date')
            ->where('end_date', '<', $now)
            ->get();

        if ($expiredPromotions->isEmpty()) {
            $this->info('No expired promotions found.');
            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($expiredPromotions as $promotion) {
            $promotion->update(['is_active' => false]);
            $this->line("Disabled promotion: {$promotion->name} (ended: {$promotion->end_date->format('Y-m-d')})");
            $count++;
        }

        $this->info("Successfully disabled {$count} expired promotion(s).");

        return Command::SUCCESS;
    }
}

