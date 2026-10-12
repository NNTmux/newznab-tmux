<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\UsenetGroup;
use Illuminate\Foundation\Http\FormRequest;

class AdminGroupListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach ($this->all() as $key => $value) {
            if (is_string($value)) {
                $this->merge([$key => trim($value) === '' ? null : trim($value)]);
            }
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $rules = [
            'groupname' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'string', 'in:0,1'],
            'backfill' => ['nullable', 'string', 'in:0,1'],
            'sort' => ['nullable', 'string', 'in:'.implode(',', array_keys(UsenetGroup::LIST_SORT_COLUMNS))],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];

        foreach (['first_post', 'last_post', 'last_updated'] as $field) {
            $rules[$field.'_from'] = ['nullable', 'date_format:Y-m-d'];
            $rules[$field.'_to'] = ['nullable', 'date_format:Y-m-d'];
            if ($this->filled($field.'_from')) {
                $rules[$field.'_to'][] = 'after_or_equal:'.$field.'_from';
            }
        }

        foreach (['releases', 'min_files', 'min_size', 'backfill_days'] as $field) {
            $type = $field === 'min_size' ? 'numeric' : 'integer';
            $maximum = $field === 'min_size' ? '8796093022207' : (string) PHP_INT_MAX;
            $rules[$field.'_min'] = ['nullable', $type, 'min:0', 'max:'.$maximum];
            $rules[$field.'_max'] = ['nullable', $type, 'min:0', 'max:'.$maximum];
            if ($this->filled($field.'_min')) {
                $rules[$field.'_max'][] = 'gte:'.$field.'_min';
            }
        }

        return $rules;
    }

    public function groupName(): string
    {
        $value = $this->validated('groupname', '');

        return is_string($value) ? $value : '';
    }

    /**
     * @return array<string, mixed>
     */
    public function listOptions(): array
    {
        $options = array_filter(
            $this->safe()->except(['groupname', 'page']),
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );

        return $options + ['sort' => 'name', 'direction' => 'asc'];
    }
}
