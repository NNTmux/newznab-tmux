<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ConfigurationDomain;
use App\Support\Configuration\SettingsPageCatalog;
use App\Support\SizeUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateDomainConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $domain = ConfigurationDomain::from((string) $this->route('domain'));
        $rules = [];

        foreach (app(SettingsPageCatalog::class)->fieldsForPage($domain) as $field) {
            $rules[$field->column] = $field->rules;
            if ($domain === ConfigurationDomain::PostProcessing && in_array($field->column, [...SettingsPageCatalog::MOVIE_PROCESSING_COLUMNS, ...SettingsPageCatalog::VIDEO_PANE_COLUMNS], true)) {
                array_unshift($rules[$field->column], 'sometimes');
            }
            if ($field->control === 'bytes') {
                $rules[$field->column.'_unit'] = ['required', 'in:'.implode(',', SizeUnit::UNITS)];
            }
            if ($field->sensitive) {
                $rules['clear_'.$field->column] = ['nullable', 'boolean'];
            }
        }

        if ($domain === ConfigurationDomain::Site) {
            $rules['remove_site_logo'] = ['nullable', 'boolean'];
        }

        if ($domain === ConfigurationDomain::Tmux) {
            $rules['cleanup_rules.*'] = ['string', 'in:'.implode(',', SettingsPageCatalog::cleanupRules())];
        }

        return $rules;
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $domain = ConfigurationDomain::from((string) $this->route('domain'));

            if ($domain === ConfigurationDomain::PostProcessing || $domain === ConfigurationDomain::Ingestion) {
                $this->validateSizePairs($validator, $domain);
            }

            if ($domain === ConfigurationDomain::PostProcessing) {
                $pattern = (string) $this->input('inner_file_blacklist', '');
                if ($pattern !== '' && @preg_match($pattern, '') === false) {
                    $validator->errors()->add('inner_file_blacklist', 'The inner file blacklist must be a valid regular expression.');
                }
            }

            if ($domain === ConfigurationDomain::Tmux) {
                $start = (int) $this->input('colors_start');
                $end = (int) $this->input('colors_end');
                if ($start >= $end) {
                    $validator->errors()->add('colors_end', 'The ending color must be greater than the starting color.');
                }

                $rawColors = preg_split('/[\s,]+/', trim((string) $this->input('color_exclusions', '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                foreach ($rawColors as $rawColor) {
                    if (preg_match('/^\d+$/', $rawColor) !== 1 || (int) $rawColor > 255) {
                        $validator->errors()->add('color_exclusions', "Excluded color {$rawColor} must be an integer from 0 through 255.");

                        continue;
                    }

                    $color = (int) $rawColor;
                    if ($color < $start || $color > $end) {
                        $validator->errors()->add('color_exclusions', "Excluded color {$color} must be inside the configured color range.");
                    }
                }
            }
        }];
    }

    /** @return list<int> */
    public function colorExclusions(): array
    {
        $values = preg_split('/[\s,]+/', trim((string) $this->input('color_exclusions', '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_map('intval', $values)));
    }

    private function validateSizePairs(Validator $validator, ConfigurationDomain $domain): void
    {
        $pairs = $domain === ConfigurationDomain::Ingestion
            ? [['min_size_to_form_release', 'max_size_to_form_release']]
            : [
                ['min_size_to_post_process', 'max_size_to_post_process'],
                ['min_size_to_process_nfo', 'max_size_to_process_nfo'],
            ];

        foreach ($pairs as [$minimum, $maximum]) {
            $minimumBytes = SizeUnit::toBytes($this->input($minimum), (string) $this->input($minimum.'_unit', 'MB'));
            $maximumBytes = SizeUnit::toBytes($this->input($maximum), (string) $this->input($maximum.'_unit', 'MB'));
            if ($maximumBytes > 0 && $minimumBytes > $maximumBytes) {
                $validator->errors()->add($maximum, 'The maximum size must be zero (unlimited) or at least the minimum size.');
            }
        }
    }
}
