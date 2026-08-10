<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTmuxSettingsRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'binarythreads' => ['required', 'integer', 'min:1', 'max:99'],
            'backfillthreads' => ['required', 'integer', 'min:1', 'max:99'],
            'releasethreads' => ['required', 'integer', 'min:1', 'max:99'],
            'postthreads' => ['required', 'integer', 'min:1', 'max:99'],
            'nfothreads' => ['required', 'integer', 'min:1', 'max:16'],
            'postthreadsnon' => ['required', 'integer', 'min:1', 'max:99'],
            'postthreadsamazon' => ['required', 'integer', 'min:1', 'max:99'],
            'fixnamethreads' => ['required', 'integer', 'min:1', 'max:16'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'binarythreads' => 'Update Binaries Threads',
            'backfillthreads' => 'Backfill Threads',
            'releasethreads' => 'Update Releases Threads',
            'postthreads' => 'Postprocessing Additional Threads',
            'nfothreads' => 'NFO Threads',
            'postthreadsnon' => 'Postprocessing Video Metadata Threads',
            'postthreadsamazon' => 'Amazon Postprocessing Threads',
            'fixnamethreads' => 'Fix Release Names Threads',
        ];
    }
}
