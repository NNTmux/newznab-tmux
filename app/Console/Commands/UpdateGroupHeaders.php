<?php

namespace App\Console\Commands;

use App\Models\UsenetGroup;
use App\Services\Binaries\BinariesService;
use App\Services\NNTP\NNTPService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateGroupHeaders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'group:update-headers {group : Group name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update a single group\'s article headers';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $groupName = $this->argument('group');

        try {
            $nntp = $this->getNntp();
            $groupMySQL = UsenetGroup::getByName($groupName)->toArray();

            if ($groupMySQL === null) {
                $this->error("Group not found: {$groupName}");

                return self::FAILURE;
            }

            $binaries = new BinariesService;
            $binaries->setNntp($nntp);
            $binaries->updateGroup($groupMySQL);

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
    private function getNntp(): NNTPService
    {
        $nntp = new NNTPService;

        $connectResult = config('nntmux_nntp.use_alternate_nntp_server') === true
            ? $nntp->doConnect(false, true)
            : $nntp->doConnect();

        if ($connectResult !== true) {
            $errorMessage = 'Unable to connect to usenet.';
            if (NNTPService::isError($connectResult)) {
                $errorMessage .= ' Error: '.$connectResult->getMessage();
            }
            throw new \Exception($errorMessage);
        }

        return $nntp;
    }
}
