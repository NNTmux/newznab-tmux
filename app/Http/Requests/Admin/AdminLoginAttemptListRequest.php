<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Spatie\SuspiciousLogin\Enums\RiskLevel;

class AdminLoginAttemptListRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasRole('Admin');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:'.implode(',', LoginAttempt::STATUSES)],
            'user' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'ip' => ['nullable', 'string', 'max:45', 'regex:/^[0-9a-fA-F:.]+$/'],
            'risk' => ['nullable', 'in:'.implode(',', self::riskOptions())],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Filters for LoginAttempt::scopeFilter().
     *
     * @return array{status: string, user: string, user_id: string, ip: string, risk: string, date_from: string, date_to: string}
     */
    public function filters(): array
    {
        return [
            'status' => $this->scalarInput('status'),
            'user' => $this->scalarInput('user'),
            'user_id' => $this->scalarInput('user_id'),
            'ip' => $this->scalarInput('ip'),
            'risk' => $this->scalarInput('risk'),
            'date_from' => $this->scalarInput('date_from'),
            'date_to' => $this->scalarInput('date_to'),
        ];
    }

    /**
     * Risk filter values: every level above low together, then each level on its own.
     *
     * @return list<string>
     */
    public static function riskOptions(): array
    {
        return [LoginAttempt::RISK_SUSPICIOUS, ...array_column(RiskLevel::cases(), 'value')];
    }

    private function scalarInput(string $key): string
    {
        $value = $this->input($key, '');

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
