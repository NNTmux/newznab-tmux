<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Security\IpBlockService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'ip-block:lift')]
class LiftIpBlock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ip-block:lift {ip : The blocked IP address}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lift the site-wide block on an IP address (works when the admin area is unreachable)';

    public function __construct(private readonly IpBlockService $blocks)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $ip = $this->blocks->normalize((string) $this->argument('ip'));

        if ($ip === null) {
            $this->error('That is not a valid IP address.');

            return Command::FAILURE;
        }

        if (! $this->blocks->lift($ip)) {
            $this->warn("{$ip} is not blocked.");

            return Command::SUCCESS;
        }

        $this->info("Lifted the block on {$ip}.");

        return Command::SUCCESS;
    }
}
