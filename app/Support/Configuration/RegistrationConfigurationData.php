<?php

declare(strict_types=1);

namespace App\Support\Configuration;

use App\Enums\RegistrationStatus;
use App\Models\RegistrationConfiguration;

final readonly class RegistrationConfigurationData
{
    public function __construct(public RegistrationStatus $status) {}

    public static function defaults(): self
    {
        return new self(RegistrationStatus::Open);
    }

    public static function fromModel(RegistrationConfiguration $model): self
    {
        return new self($model->status);
    }

    /** @return array{status: int} */
    public function toArray(): array
    {
        return ['status' => $this->status->value];
    }
}
