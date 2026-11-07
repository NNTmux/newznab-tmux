<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Support\Navigation\ListFilters;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class SortDropdown extends Component
{
    /**
     * The current sort value.
     */
    public string $currentSort;

    /**
     * Available sort options.
     *
     * @var array<string, array{label: string, icon: string}>
     */
    public array $sortOptions;

    /**
     * The base URL for sorting links.
     */
    public string $baseUrl;

    /**
     * Query parameters to preserve.
     *
     * @var array<string, mixed>
     */
    public array $queryParams;

    /**
     * Current sort label.
     */
    public string $currentLabel;

    /**
     * Current sort icon.
     */
    public string $currentIcon;

    /**
     * Pre-computed sort URLs.
     *
     * @var array<string, string>
     */
    public array $sortUrls;

    /**
     * Create a new component instance.
     *
     * @param  array<string, array{label: string, icon: string}>|null  $options
     * @param  array<string, mixed>|null  $queryParams
     * @param  list<string>|null  $ordering  Controller ordering keys (e.g. getBrowseOrdering()); adds readable labels and a "Default order" entry
     */
    public function __construct(
        ?string $currentSort = null,
        ?array $options = null,
        ?string $baseUrl = null,
        ?array $queryParams = null,
        ?array $ordering = null,
    ) {
        $this->baseUrl = $baseUrl ?? request()->url();
        $this->queryParams = $queryParams ?? request()->except(['ob', 'page']);

        // A controller ordering list: readable labels plus "Default order" for requests without ?ob=.
        if ($ordering !== null) {
            $options = ['' => ['label' => __('Default order'), 'icon' => 'fa-arrow-down-wide-short']] + ListFilters::sortOptions($ordering);
            $requested = request('ob');
            $currentSort ??= is_string($requested) && isset($options[$requested]) ? $requested : '';
        }

        $this->currentSort = $currentSort ?? request('ob', 'posted_desc');

        // Default sort options for releases
        $this->sortOptions = $options ?? [
            'posted_desc' => ['label' => 'Posted (Newest)', 'icon' => 'fa-calendar-alt'],
            'posted_asc' => ['label' => 'Posted (Oldest)', 'icon' => 'fa-calendar-alt'],
            'added_desc' => ['label' => 'Added (Newest)', 'icon' => 'fa-clock'],
            'added_asc' => ['label' => 'Added (Oldest)', 'icon' => 'fa-clock'],
            'name_asc' => ['label' => 'Name (A-Z)', 'icon' => 'fa-font'],
            'name_desc' => ['label' => 'Name (Z-A)', 'icon' => 'fa-font'],
            'size_desc' => ['label' => 'Size (Largest)', 'icon' => 'fa-hdd'],
            'size_asc' => ['label' => 'Size (Smallest)', 'icon' => 'fa-hdd'],
            'files_desc' => ['label' => 'Files (Most)', 'icon' => 'fa-file'],
            'files_asc' => ['label' => 'Files (Least)', 'icon' => 'fa-file'],
            'stats_desc' => ['label' => 'Grabs (Most)', 'icon' => 'fa-download'],
            'stats_asc' => ['label' => 'Grabs (Least)', 'icon' => 'fa-download'],
        ];

        // Pre-compute current label and icon
        $this->currentLabel = $this->sortOptions[$this->currentSort]['label'] ?? 'Posted (Newest)';
        $this->currentIcon = $this->sortOptions[$this->currentSort]['icon'] ?? 'fa-calendar-alt';

        // Pre-compute all sort URLs
        $this->sortUrls = [];
        foreach ($this->sortOptions as $sortKey => $sortData) {
            $params = $sortKey === '' ? $this->queryParams : array_merge($this->queryParams, ['ob' => $sortKey]);
            $this->sortUrls[$sortKey] = $params === [] ? $this->baseUrl : $this->baseUrl.'?'.http_build_query($params);
        }
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.sort-dropdown');
    }
}
