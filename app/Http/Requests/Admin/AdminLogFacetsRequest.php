<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

/**
 * Validates level / channel facet requests: the same term and filters as a search, none of them required.
 */
class AdminLogFacetsRequest extends AdminLogSearchRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->filterRules();
    }
}
