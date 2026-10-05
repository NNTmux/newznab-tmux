<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RegistrationStatus;
use App\Models\Concerns\IsSingletonConfiguration;
use Illuminate\Database\Eloquent\Model;

/** @property RegistrationStatus $status */
final class RegistrationConfiguration extends Model
{
    use IsSingletonConfiguration;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => RegistrationStatus::class];
    }
}
