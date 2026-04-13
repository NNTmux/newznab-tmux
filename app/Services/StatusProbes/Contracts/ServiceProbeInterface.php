<?php

declare(strict_types=1);

namespace App\Services\StatusProbes\Contracts;

use App\Services\StatusProbes\ProbeResult;

interface ServiceProbeInterface
{
    public function identifier(): string;

    public function probe(): ProbeResult;
}
