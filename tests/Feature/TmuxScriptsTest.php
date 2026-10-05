<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TmuxScriptsTest extends TestCase
{
    /** @param list<string> $input
     * @param  list<string>  $expected
     */
    #[DataProvider('wrapperProvider')]
    public function test_wrappers_preserve_literal_arguments_and_worker_status(string $name, array $input, array $expected): void
    {
        $root = sys_get_temp_dir().'/nntmux-script-'.bin2hex(random_bytes(8));
        $scripts = $root.'/app/Services/Tmux/Scripts';
        mkdir($scripts, 0700, true);
        copy(base_path('app/Services/Tmux/Scripts/'.$name.'.php'), $scripts.'/'.$name.'.php');
        file_put_contents($root.'/artisan', '<?php echo json_encode(array_slice($argv, 1)); exit(7);');
        try {
            $process = new Process([PHP_BINARY, $scripts.'/'.$name.'.php', ...$input], $root);
            $process->run();
            $this->assertSame(7, $process->getExitCode(), $process->getErrorOutput());
            $this->assertSame($expected, json_decode($process->getOutput(), true));
        } finally {
            app('files')->deleteDirectory($root);
        }
    }

    /** @return array<string, array{string, list<string>, list<string>}> */
    public static function wrapperProvider(): array
    {
        return [
            'monitor' => ['monitor', ['--session=custom session; literal'], ['tmux:monitor', '--session=custom session; literal']],
            'groups' => ['update_groups', [], ['groups:update']],
            'predb zero' => ['postprocess_pre', ['0'], ['predb:check', '0']],
            'fix names' => ['groupfixrelnames', ['standard a 25 1 2'], ['releases:fix-names-group', 'standard', '--guid-char=a', '--limit=25']],
            'predb names' => ['groupfixrelnames', ['predbft a 25 1 2'], ['releases:fix-names-group', 'predbft', '--limit=25', '--thread=1', '--workers=2']],
        ];
    }

    public function test_installer_stops_on_failure_and_cleans_only_its_unique_temporary_directory(): void
    {
        $root = sys_get_temp_dir().'/nntmux-install-'.bin2hex(random_bytes(8));
        mkdir($root.'/bin', 0700, true);
        mkdir($root.'/tmux', 0700);
        file_put_contents($root.'/tmux/keep', 'unrelated');
        file_put_contents($root.'/bin/sudo', "#!/bin/sh\nexit 0\n");
        file_put_contents($root.'/bin/git', "#!/bin/sh\nexit 9\n");
        chmod($root.'/bin/sudo', 0700);
        chmod($root.'/bin/git', 0700);
        try {
            $process = new Process(['bash', base_path('install_tmux.sh')], base_path(), ['PATH' => $root.'/bin:'.getenv('PATH'), 'TMPDIR' => $root]);
            $process->run();
            $this->assertSame(9, $process->getExitCode());
            $this->assertSame('unrelated', file_get_contents($root.'/tmux/keep'));
            $this->assertSame([], glob($root.'/nntmux-tmux.*'));
        } finally {
            app('files')->deleteDirectory($root);
        }
    }
}
