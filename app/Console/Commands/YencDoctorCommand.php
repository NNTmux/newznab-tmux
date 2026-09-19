<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Yenc\NativePayloadDecoder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Throwable;

class YencDoctorCommand extends Command
{
    protected $signature = 'nntmux:yenc-doctor
                                {--json : Output machine-readable JSON}';

    protected $description = 'Diagnose yEnc decoder configuration and RapidYenc native library availability';

    public function handle(): int
    {
        $mode = Config::string('yenc.decoder', 'auto');
        $library = Config::string('yenc.native_library', '');
        $report = [
            'php' => PHP_VERSION,
            'os' => PHP_OS_FAMILY,
            'sapi' => PHP_SAPI,
            'thread_safe' => (bool) PHP_ZTS,
            'ffi_extension' => extension_loaded('FFI'),
            'ffi_enable' => (string) ini_get('ffi.enable'),
            'decoder_mode' => $mode,
            'configuration_error' => in_array($mode, ['auto', 'php', 'native'], true)
                ? null : 'YENC_DECODER must be auto, php or native.',
            'library_path' => $library,
            'library_present' => $library !== '' && is_file($library),
            'native_available' => false,
            'native_error' => null,
            'rapidyenc_version' => null,
            'hardware_crc32' => false,
        ];
        try {
            $native = new NativePayloadDecoder($library);
            $report['native_available'] = true;
            $report['rapidyenc_version'] = $native->version();
            $report['hardware_crc32'] = $native->crc32('') !== null;
        } catch (Throwable $exception) {
            $report['native_error'] = $exception->getMessage();
        }
        $report['active_decoder'] = match (true) {
            $report['configuration_error'] !== null => 'unavailable',
            $mode === 'php' => 'PHP',
            $report['native_available'] => 'RapidYenc',
            $mode === 'native' => 'unavailable',
            default => 'PHP',
        };

        $json = (bool) $this->option('json');
        if ($json) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Check', 'Value'], [
                ['PHP', $report['php'].' ('.$report['os'].', '.$report['sapi'].($report['thread_safe'] ? ', ZTS' : ', NTS').')'],
                ['FFI extension', $report['ffi_extension'] ? 'loaded' : 'missing'],
                ['ffi.enable', $report['ffi_enable'] === '' ? '(default)' : $report['ffi_enable']],
                ['YENC_DECODER', $report['decoder_mode']],
                ['Library', $report['library_path'] === '' ? '(unset)' : $report['library_path']],
                ['Library present', $report['library_present'] ? 'yes' : 'no'],
                ['Native self-check', $report['native_available'] ? 'passed' : 'failed: '.$report['native_error']],
                ['RapidYenc version', $report['rapidyenc_version'] ?? 'n/a'],
                ['Hardware CRC32', $report['hardware_crc32'] ? 'yes' : 'no'],
                ['Active decoder', $report['active_decoder']],
            ]);
        }

        if ($report['configuration_error'] !== null) {
            if (! $json) {
                $this->error($report['configuration_error']);
            }

            return self::FAILURE;
        }
        if ($mode === 'native' && ! $report['native_available']) {
            if (! $json) {
                $this->error('YENC_DECODER=native but the RapidYenc library is unusable.');
            }

            return self::FAILURE;
        }
        if ($json) {
            return self::SUCCESS;
        }
        if ($mode !== 'php' && ! $report['native_available']) {
            $this->warn('RapidYenc is unavailable; decoding uses the slower PHP fallback.');
            $this->line('Run scripts/build-rapidyenc.sh on Linux CLI to build it, then restart CLI workers.');
        } elseif ($report['native_available'] && ! $report['hardware_crc32']) {
            $this->warn('The loaded library predates CRC support; rerun scripts/build-rapidyenc.sh to enable hardware CRC32.');
        }

        return self::SUCCESS;
    }
}
