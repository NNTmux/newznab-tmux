<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class EditSelectedGroupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'group_ids' => $this->decodeJsonInput('group_ids'),
            'changes' => $this->decodeJsonInput('changes'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'in:edit_selected_groups'],
            'group_ids' => ['required', 'array', 'min:1'],
            'group_ids.*' => ['required', 'integer', 'distinct', 'exists:usenet_groups,id'],
            'changes' => ['required', 'array:backfill_target,minfilestoformrelease,minsizetoformrelease,active,backfill', 'min:1'],
            'changes.backfill_target' => ['sometimes', 'integer', 'between:1,7300'],
            'changes.minfilestoformrelease' => ['sometimes', 'integer', 'between:0,2147483647'],
            'changes.minsizetoformrelease' => [
                'sometimes',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) && ! is_int($value)) {
                        $fail('The minimum file size must be a whole byte count or use M, MB, G, or GB.');

                        return;
                    }

                    try {
                        parse_group_file_size($value);
                    } catch (InvalidArgumentException $exception) {
                        $fail($exception->getMessage());
                    }
                },
            ],
            'changes.active' => ['sometimes', 'integer', 'in:0,1'],
            'changes.backfill' => ['sometimes', 'integer', 'in:0,1'],
        ];
    }

    /**
     * @return list<int>
     */
    public function groupIds(): array
    {
        return array_map('intval', $this->validated('group_ids'));
    }

    /**
     * @return array<string, int|null>
     */
    public function changes(): array
    {
        /** @var array<string, mixed> $changes */
        $changes = $this->validated('changes');

        if (array_key_exists('minsizetoformrelease', $changes)) {
            $parsed = parse_group_file_size($changes['minsizetoformrelease']);
            $changes['minsizetoformrelease'] = $parsed === 0 ? null : $parsed;
        }

        if (array_key_exists('minfilestoformrelease', $changes)) {
            $minimumFiles = (int) $changes['minfilestoformrelease'];
            $changes['minfilestoformrelease'] = $minimumFiles === 0 ? null : $minimumFiles;
        }

        foreach (['backfill_target', 'active', 'backfill'] as $key) {
            if (array_key_exists($key, $changes)) {
                $changes[$key] = (int) $changes[$key];
            }
        }

        /** @var array<string, int|null> $changes */
        return $changes;
    }

    private function decodeJsonInput(string $key): mixed
    {
        $value = $this->input($key);

        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
