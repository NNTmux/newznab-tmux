<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\RegistrationPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RegistrationPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_enabled' => $this->boolean('is_enabled'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'is_enabled' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! $this->boolean('is_enabled')) {
                    return;
                }

                $startsAt = $this->date('starts_at');
                $endsAt = $this->date('ends_at');

                if ($startsAt === null || $endsAt === null) {
                    return;
                }

                $period = $this->route('period');
                $periodId = $period instanceof RegistrationPeriod ? $period->id : (is_numeric($period) ? (int) $period : null);

                $overlaps = RegistrationPeriod::query()
                    ->where('is_enabled', true)
                    ->when($periodId !== null, fn ($query) => $query->where('id', '!=', $periodId))
                    ->where(function ($query) use ($startsAt, $endsAt) {
                        $query->whereBetween('starts_at', [$startsAt, $endsAt])
                            ->orWhereBetween('ends_at', [$startsAt, $endsAt])
                            ->orWhere(function ($query) use ($startsAt, $endsAt) {
                                $query->where('starts_at', '<=', $startsAt)
                                    ->where('ends_at', '>=', $endsAt);
                            });
                    })
                    ->exists();

                if ($overlaps) {
                    $validator->errors()->add('starts_at', 'Enabled registration periods cannot overlap.');
                }
            },
        ];
    }
}
