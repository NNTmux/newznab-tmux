<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Releases\ReleaseBrowseService;
use App\Support\SizeUnit;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class SearchReleaseListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach ($this->all() as $field => $value) {
            if (is_string($value)) {
                $this->merge([$field => trim($value) === '' ? null : trim($value)]);
            }
        }
        foreach (['search' => 'searchadvr', 't' => 'searchadvcat', 'group' => 'searchadvgroups', 'minage' => 'searchadvdaysnew', 'maxage' => 'searchadvdaysold'] as $field => $alias) {
            if (! $this->has($field) && $this->has($alias)) {
                $value = $this->input($alias);
                $this->merge([$field => $field !== 't' && $value === '-1' ? null : $value]);
            }
        }
        if (! $this->has('search') && ($this->has('subject') || $this->has('id'))) {
            $this->merge(['search' => $this->input('subject', $this->input('id'))]);
        }
        // Legacy buckets retain their existing byte bounds, displayed as MB in the modern form.
        $buckets = [1 => 100, 2 => 200, 3 => 500, 4 => 1000, 5 => 2000, 6 => 3000, 7 => 4000, 8 => 8000, 9 => 16000, 10 => 32000, 11 => 64000];
        foreach (['minsize' => 'searchadvsizefrom', 'maxsize' => 'searchadvsizeto'] as $field => $alias) {
            $value = $this->input($alias);
            if (! $this->has($field) && is_scalar($value) && isset($buckets[(int) $value])) {
                $this->merge([$field => (string) $buckets[(int) $value]]);
            }
        }
        $mode = $this->input('search_type', 'basic');
        if ($mode === null || $mode === 'basic') {
            $mode = $this->anyFilled(['group', 'searchadvposter', 'searchadvsubject', 'searchadvfilename', 'minage', 'maxage', 'minsize', 'maxsize', 'added_from', 'added_to', 'minfiles', 'maxfiles', 'mingrabs', 'maxgrabs', 'mincomments', 'maxcomments']) ? 'adv' : 'basic';
        }
        $this->merge(['search_type' => $mode, 'ob' => $this->input('ob') ?? 'posted_desc']);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $rules = [
            'search' => ['nullable', 'string', 'max:1000'],
            't' => ['nullable', 'string', 'max:512', 'regex:/^(?:-1|\d+(?:,\d+)*)$/'],
            'group' => ['nullable', 'string', 'max:255'],
            'search_type' => ['nullable', 'string', 'in:basic,adv'],
            'ob' => ['nullable', 'string', 'in:'.implode(',', (new ReleaseBrowseService)->getBrowseOrdering())],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'added_from' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1970-01-01'],
            'added_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1970-01-01'],
        ];
        if ($this->filled('added_from')) {
            $rules['added_to'][] = 'after_or_equal:added_from';
        }
        foreach (['size', 'age', 'files', 'grabs', 'comments'] as $field) {
            $type = $field === 'size' ? 'numeric' : 'integer';
            $maximum = match ($field) {
                'size' => '8796093022207',
                'age' => '3650000',
                default => '4294967295',
            };
            $rules['min'.$field] = ['nullable', $type, 'min:0', 'max:'.$maximum];
            $rules['max'.$field] = ['nullable', $type, 'min:0', 'max:'.$maximum];
            if ($this->filled('min'.$field)) {
                $rules['max'.$field][] = 'gte:min'.$field;
            }
        }
        foreach (['id', 'subject', 'searchadvr', 'searchadvsubject', 'searchadvposter', 'searchadvfilename', 'searchadvcat', 'searchadvgroups'] as $field) {
            $rules[$field] = ['nullable', 'string', 'max:1000'];
        }
        foreach (['searchadvdaysnew', 'searchadvdaysold'] as $field) {
            $rules[$field] = ['nullable', 'integer', 'min:-1', 'max:3650000'];
        }
        foreach (['searchadvsizefrom', 'searchadvsizeto'] as $field) {
            $rules[$field] = ['nullable', 'integer', 'min:-1', 'max:11'];
        }

        return $rules;
    }

    /** @return array<string, mixed> */
    public function listQuery(): array
    {
        $query = array_filter($this->safe()->except(['page', 'id', 'subject', 'searchadvr', 'searchadvcat', 'searchadvgroups', 'searchadvdaysnew', 'searchadvdaysold', 'searchadvsizefrom', 'searchadvsizeto']), static fn (mixed $value): bool => $value !== null && $value !== '');

        return $query + ['search' => '', 't' => '', 'ob' => 'posted_desc', 'search_type' => 'basic'];
    }

    /** @return list<int> */
    public function categoryIds(): array
    {
        $value = $this->validated('t');

        return is_string($value) && $value !== '' && $value !== '0' && $value !== '-1'
            ? array_values(array_unique(array_map('intval', explode(',', $value))))
            : [-1];
    }

    /** @return array<string, array<string, int>> */
    public function ranges(): array
    {
        $ranges = [];
        foreach (['size' => 'size', 'files' => 'totalpart', 'grabs' => 'grabs', 'comments' => 'comments'] as $field => $column) {
            foreach (['min', 'max'] as $bound) {
                $value = $this->validated($bound.$field);
                if ($value !== null && $value !== '') {
                    $ranges[$column][$bound] = $field === 'size' ? SizeUnit::toBytes($value, 'MB') : (int) $value;
                }
            }
        }
        foreach (['from' => 'min', 'to' => 'max'] as $field => $bound) {
            $value = $this->validated('added_'.$field);
            if (is_string($value) && $value !== '') {
                $date = CarbonImmutable::parse($value, config('app.timezone', 'UTC'));
                $ranges['adddate_ts'][$bound] = ($bound === 'min' ? $date->startOfDay() : $date->endOfDay())->getTimestamp();
            }
        }

        return $ranges;
    }
}
