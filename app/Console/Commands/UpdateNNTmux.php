<?php

namespace App\Console\Commands;

use Blacklight\Tmux;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Process;
use Ytake\LaravelSmarty\Smarty;

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
     */
    public function handle(): void
    {
        $maintenance = $this->appDown();
        $running = $this->stopTmux();

        try {
            $output = $this->call('nntmux:git');
            if ($output === 'Already up-to-date.') {
                $this->info($output);
            } else {
                $status = $this->call('nntmux:composer');
                if ($status) {
                    $this->error('Composer failed to update!!');
                }
                $fail = $this->call('nntmux:db');
                if ($fail) {
                    $this->error('Db updating failed!!');
                }
                // Install npm packages
                Process::run('npm install')->output();
                // Run npm build
                Process::run('npm run build')->output();
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }

        $cleared = (new Smarty())->setCompileDir(config('ytake-laravel-smarty.compile_path'))->clearCompiledTemplate();
        if ($cleared) {
            $this->output->writeln('<comment>The Smarty compiled template cache has been cleaned for you</comment>');
        } else {
            $this->output->writeln(
                '<comment>You should clear your Smarty compiled template cache at: '.
                config('ytake-laravel-smarty.compile_path').'</comment>'
            );
        }

        if ($maintenance === true) {
            $this->call('up');
        }
        if ($running === true) {
            $this->startTmux();
        }
    }

    private function appDown(): bool
    {
        if (App::isDownForMaintenance() === false) {
            $this->call('down', ['--render' => 'errors::maintenance', '--retry' => 120]);

            return true;
        }

        return false;
    }

    private function stopTmux(): bool
    {
        if ((new Tmux())->isRunning() === true) {
            $this->call('tmux-ui:stop', ['--kill' => true]);

            return true;
        }

        return false;
    }

    private function startTmux(): void
    {
        $this->call('tmux-ui:start');
    }
}
