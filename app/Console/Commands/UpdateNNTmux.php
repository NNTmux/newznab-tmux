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
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }

        // Install npm packages
        $this->info('Installing npm packages...');
        $process = Process::timeout(360)->run('npm install');
        echo $process->output();
        echo $process->errorOutput();
        $this->info('Npm packages installed successfully!');
        // Run npm build
        $this->info('Building assets...');
        $process = Process::timeout(360)->run('npm run build');
        echo $process->output();
        echo $process->errorOutput();
        $this->info('Assets built successfully!');

        $cleared = (new Smarty)->setCompileDir(config('ytake-laravel-smarty.compile_path'))->clearCompiledTemplate();
        if ($cleared) {
            $this->output->writeln('<comment>The Smarty compiled template cache has been cleaned for you</comment>');
        } else {
            $this->output->writeln(
                '<comment>You should clear your Smarty compiled template cache at: '.
                config('ytake-laravel-smarty.compile_path').'</comment>'
            );
        }

        // Merge changes from .env.example into .env
        $this->info('Merging changes from .env.example into .env...');

        try {
            // Read both files
            $envExampleContent = file_get_contents(base_path('.env.example'));
            $envContent = file_get_contents(base_path('.env'));

            if ($envExampleContent === false || $envContent === false) {
                throw new \Exception('Could not read .env or .env.example files');
            }

            // Parse files into key-value pairs
            $envExampleVars = [];
            foreach (preg_split("/\r\n|\n|\r/", $envExampleContent) as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }

                if (preg_match('/^([^=]+)=(.*)$/', $line, $matches)) {
                    $key = trim($matches[1]);
                    $value = $matches[2]; // Keep the original value with potential = signs
                    $envExampleVars[$key] = $value;
                }
            }

            $envVars = [];
            foreach (preg_split("/\r\n|\n|\r/", $envContent) as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }

                if (preg_match('/^([^=]+)=(.*)$/', $line, $matches)) {
                    $key = trim($matches[1]);
                    $value = $matches[2];
                    $envVars[$key] = $value;
                }
            }

            // Find keys in .env.example that are not in .env
            $missingKeys = array_diff_key($envExampleVars, $envVars);

            if (empty($missingKeys)) {
                $this->info('No new keys found in .env.example to merge into .env');
            } else {
                // Add missing keys to .env file
                $newEnvContent = $envContent;
                if (! str_ends_with($newEnvContent, "\n")) {
                    $newEnvContent .= "\n";
                }
                $newEnvContent .= "\n# New settings added from .env.example\n";

                foreach ($missingKeys as $key => $value) {
                    $newEnvContent .= "$key=$value\n";
                }

                // Write updated content back to .env
                if (file_put_contents(base_path('.env'), $newEnvContent)) {
                    $this->info('Successfully merged '.count($missingKeys).' new keys from .env.example into .env');
                    $this->line('The following keys were added:');
                    foreach ($missingKeys as $key => $value) {
                        $this->line("  $key=$value");
                    }
                } else {
                    throw new \Exception('Failed to write changes to .env file');
                }
            }

        } catch (\Exception $e) {
            $this->error('Failed to merge changes: '.$e->getMessage());
            $this->info('There are changes in .env.example that need to be added manually to .env');
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
        if ((new Tmux)->isRunning() === true) {
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
