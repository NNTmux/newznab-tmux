<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Ytake\LaravelSmarty\Smarty;
use Illuminate\Support\Facades\App;

class UpdateNNTmux extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update NNTmux installation';

    /**
     * @var \app\extensions\util\Git object.
     */
    protected $git;

    /**
     * @var array Decoded JSON updates file.
     */
    protected $updates = null;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            $maintenance = App::isDownForMaintenance();

            if (! $maintenance) {
                $this->call('down');
            }

            $output = $this->call('nntmux:git');
            if ($output === 'Already up-to-date.') {
                $this->info($output);
            } else {
                $status = $this->call('nntmux:composer');
                if ($status) {
                    $this->error('Composer failed to update!!');

                    return false;
                }
                $fail = $this->call('nntmux:db');
                if ($fail) {
                    $this->error('Db updating failed!!');

                    return 1;
                }
            }

            $smarty = new Smarty();
            $smarty->setCompileDir(config('ytake-laravel-smarty.compile_path'));
            $cleared = $smarty->clearCompiledTemplate();
            if ($cleared) {
                $this->output->writeln('<comment>The Smarty compiled template cache has been cleaned for you</comment>');
            } else {
                $this->output->writeln(
                    '<comment>You should clear your Smarty compiled template cache at: '.
                    config('ytake-laravel-smarty.compile_path').'</comment>'
                );
            }

            if (! $maintenance) {
                $this->call('up');
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
