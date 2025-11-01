<?php

namespace App\Console\Commands;

use App\Models\UsenetGroup;
use Blacklight\Binaries;
use Blacklight\NNTP;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PartRepair extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'binaries:part-repair {group : Group name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Do part repair for a group';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $groupName = $this->argument('group');

        try {
            $groupMySQL = UsenetGroup::getByName($groupName)->toArray();

            if ($groupMySQL === null) {
                $this->error("Group not found: {$groupName}");

                return self::FAILURE;
            }

            $nntp = $this->getNntp();

            $data = $nntp->selectGroup($groupMySQL['name']);

            if (NNTP::isError($data) && $nntp->dataError($nntp, $groupMySQL['name']) === false) {
                return self::FAILURE;
            }

            (new Binaries(['NNTP' => $nntp]))->partRepair($groupMySQL);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error($e->getTraceAsString());
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Get NNTP connection.
     */
    private function getNntp(): NNTP
    {
        $nntp = new NNTP;

        if ((config('nntmux_nntp.use_alternate_nntp_server') === true
            ? $nntp->doConnect(false, true)
            : $nntp->doConnect()) !== true) {
            throw new \Exception('Unable to connect to usenet.');
        }

        return $nntp;
    }
}
