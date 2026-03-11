<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RegistrationStatusService;
use Illuminate\Console\Command;

class DisableExpiredRegistrationPeriods extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:disable-expired-registration-periods';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Disable completed registration periods and mark them as done';

    public function __construct(
        private readonly RegistrationStatusService $registrationStatusService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expiredPeriods = $this->registrationStatusService->disableExpiredPeriods();

        if ($expiredPeriods->isEmpty()) {
            $this->info('No expired registration periods found.');

            return Command::SUCCESS;
        }

        foreach ($expiredPeriods as $period) {
            $this->line(sprintf(
                'Marked registration period as done: %s (ended: %s)',
                $period->name,
                $period->ends_at->format('Y-m-d H:i')
            ));
        }

        $this->info(sprintf(
            'Successfully completed %d expired registration period(s).',
            $expiredPeriods->count()
        ));

        return Command::SUCCESS;
    }
}
