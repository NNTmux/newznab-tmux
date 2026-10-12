@props([
    'results',
    'showThumbs' => false,
    'dateField' => 'adddate',
    'queryParams' => null,
])

@php
    $shouldShowThumbs = filter_var($showThumbs, FILTER_VALIDATE_BOOL);
    $activeDateField = (string) $dateField;
    $sortQuery = array_filter($queryParams ?? request()->query(), is_scalar(...));
    unset($sortQuery['page']);
    $requestedSort = $sortQuery['ob'] ?? 'posted_desc';
    $currentSort = is_string($requestedSort) && preg_match('/^(name|cat|added|posted|size|files|stats|comments)_(asc|desc)$/', $requestedSort) === 1 ? $requestedSort : 'posted_desc';
    $dateSort = $activeDateField === 'postdate' ? 'posted' : 'added';
    $headers = ['name' => 'Name', 'cat' => 'Category', $dateSort => $dateSort === 'posted' ? 'Posted' : 'Added', 'size' => 'Size', 'files' => 'Files', 'stats' => 'Stats'];
@endphp

<!-- Results Table (Desktop) -->
<div class="hidden md:block overflow-x-auto">
    <table class="release-results-table min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <caption class="sr-only">Indexed releases and download options</caption>
        <thead class="bg-gray-100 dark:bg-gray-900">
            <tr>
                <th scope="col" class="px-3 py-3 text-left">
                    <input type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-primary-600 dark:text-primary-500 focus:ring-primary-500 dark:focus:ring-primary-400 dark:bg-gray-700" aria-label="Select all releases" id="chkSelectAll" x-model="allChecked" @change="toggleAll()">
                </th>
                @foreach($headers as $sortKey => $label)
                    @php
                        $ascending = $currentSort === $sortKey . '_asc';
                        $descending = $currentSort === $sortKey . '_desc';
                        $nextDirection = $ascending ? 'desc' : 'asc';
                        $sortUrl = request()->url() . '?' . http_build_query(array_merge($sortQuery, ['ob' => $sortKey . '_' . $nextDirection]));
                        $sortLabel = $sortKey === 'stats' ? 'Grabs' : $label;
                    @endphp
                    <th scope="col" data-sort="{{ $sortKey }}" aria-sort="{{ $ascending ? 'ascending' : ($descending ? 'descending' : 'none') }}" class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                        <a href="{{ $sortUrl }}" class="inline-flex items-center gap-1 hover:text-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500" aria-label="Sort {{ $sortLabel }} {{ $nextDirection === 'asc' ? 'ascending' : 'descending' }}">
                            {{ $label }}
                            <i class="fas {{ $ascending ? 'fa-arrow-up' : ($descending ? 'fa-arrow-down' : 'fa-sort') }}" aria-hidden="true"></i>
                        </a>
                    </th>
                @endforeach
                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Action</th>
            </tr>
        </thead>
        <tbody class="surface-panel divide-y divide-gray-200 dark:divide-gray-700">
            @foreach($results as $result)
                @php
                    $sizeLabel = $result->size_formatted ?? number_format(($result->size ?? 0) / 1073741824, 2) . ' GB';
                    $dateValue = $result->{$activeDateField} ?? $result->adddate ?? $result->postdate ?? null;
                @endphp
                <tr class="release-result-row">
                    <td class="px-3 py-4 whitespace-nowrap">
                        <input type="checkbox" class="chkRelease rounded border-gray-300 dark:border-gray-600 text-primary-600 dark:text-primary-500 focus:ring-primary-500 dark:focus:ring-primary-400 dark:bg-gray-700" aria-label="Select {{ $result->searchname }}" name="release[]" value="{{ $result->guid }}" @change="onCheckboxChange()">
                    </td>
                    <td class="release-result-name px-3 py-4">
                        <div class="flex items-start">
                            @if($shouldShowThumbs)
                                @php
                                    $coverUrl = ($result->cover ?? false) ? $result->cover : getReleaseCover($result);
                                    $hasValidCover = $coverUrl && !str_contains($coverUrl, 'no-cover.png');
                                @endphp
                                @if($hasValidCover)
                                    <a href="{{ url('/details/' . $result->guid) }}" class="shrink-0 bg-gray-100 dark:bg-gray-700 rounded mr-3" x-show="showThumbs" @unless(request()->query('thumbs') === '1') x-cloak @endunless>
                                        <img src="{{ request()->query('thumbs') === '1' ? $coverUrl : '' }}" x-bind:src="showThumbs ? '{{ $coverUrl }}' : ''" class="w-12 h-16 object-cover rounded shadow-sm hover:shadow-md transition" alt="Cover" loading="lazy">
                                    </a>
                                @endif
                            @endif
                            <div class="min-w-0 flex-1">
                                <a href="{{ url('/details/' . $result->guid) }}" class="text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 release-result-title">{{ $result->searchname }}</a>
                                <x-release-badges :result="$result" />
                                <div class="release-result-source mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                    @if(!empty($result->group_name))
                                        <span class="min-w-0">
                                            <i class="fas fa-users mr-1"></i> {{ $result->group_name }}
                                        </span>
                                    @endif
                                    @if(!empty($result->postdate))
                                        <span class="min-w-0">
                                            <i class="fas fa-calendar mr-1"></i> Posted: {{ userDate($result->postdate, 'M d, Y H:i') }}
                                        </span>
                                    @endif
                                    @if(!empty($result->fromname))
                                        <span class="min-w-0">
                                            <i class="fas fa-user mr-1"></i> {{ $result->fromname }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="px-3 py-4 whitespace-nowrap">
                        <x-release-category-link
                            :category-name="$result->category_name ?? null"
                            :parent-category="$result->parent_category ?? null"
                            :sub-category="$result->sub_category ?? null"
                        />
                    </td>
                    <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                        {{ $dateValue ? userDateDiffForHumans($dateValue) : 'Unknown' }}
                    </td>
                    <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                        {{ $sizeLabel }}
                    </td>
                    <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                        @if(($result->totalpart ?? 0) > 0)
                            <button type="button"
                                    class="filelist-badge text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 font-medium cursor-pointer hover:underline"
                                    data-guid="{{ $result->guid }}"
                                    title="View file list">
                                {{ $result->totalpart ?? 0 }}
                            </button>
                        @else
                            {{ $result->totalpart ?? 0 }}
                        @endif
                    </td>
                    <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                        <div class="flex items-center gap-2">
                            <span title="Grabs"><i class="fas fa-download text-green-600 dark:text-green-400"></i> {{ $result->grabs ?? 0 }}</span>
                            <span title="Comments"><i class="fas fa-comment text-primary-600 dark:text-primary-400"></i> {{ $result->comments ?? 0 }}</span>
                        </div>
                    </td>
                    <td class="px-3 py-4 whitespace-nowrap">
                        <x-release-row-actions :result="$result" :compact="true" />
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<!-- Mobile Card View -->
<div class="md:hidden space-y-3 px-4 py-4">
    @foreach($results as $result)
        @php
            $sizeLabel = $result->size_formatted ?? number_format(($result->size ?? 0) / 1073741824, 2) . ' GB';
            $dateValue = $result->{$activeDateField} ?? $result->adddate ?? $result->postdate ?? null;
        @endphp
        <article class="release-result-card surface-panel border rounded-xl p-4">
            <div class="flex items-start gap-3">
                <input type="checkbox" class="chkRelease rounded border-gray-300 dark:border-gray-600 text-primary-600 dark:text-primary-500 focus:ring-primary-500 dark:focus:ring-primary-400 dark:bg-gray-700 mt-1" aria-label="Select {{ $result->searchname }}" name="release[]" value="{{ $result->guid }}" @change="onCheckboxChange()">
                <div class="flex-1 min-w-0">
                    @if($shouldShowThumbs)
                        @php
                            $mCoverUrl = ($result->cover ?? false) ? $result->cover : getReleaseCover($result);
                            $mHasCover = $mCoverUrl && !str_contains($mCoverUrl, 'no-cover.png');
                        @endphp
                        @if($mHasCover)
                            <a href="{{ url('/details/' . $result->guid) }}" class="block mb-2 bg-gray-100 dark:bg-gray-700 rounded-lg" x-show="showThumbs" @unless(request()->query('thumbs') === '1') x-cloak @endunless>
                                <img src="{{ request()->query('thumbs') === '1' ? $mCoverUrl : '' }}" x-bind:src="showThumbs ? '{{ $mCoverUrl }}' : ''" class="w-16 h-20 object-cover rounded-lg shadow-sm" alt="Cover" loading="lazy">
                            </a>
                        @endif
                    @endif
                    <a href="{{ url('/details/' . $result->guid) }}" class="text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 release-result-title">
                        {{ $result->searchname }}
                    </a>
                    <x-release-badges :result="$result" />
                    <div class="release-result-metadata mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-xs text-gray-600 dark:text-gray-400">
                        <x-release-category-link
                            :category-name="$result->category_name ?? null"
                            :parent-category="$result->parent_category ?? null"
                            :sub-category="$result->sub_category ?? null"
                        />
                        <span><i class="fas fa-clock mr-1"></i>{{ $dateValue ? userDateDiffForHumans($dateValue) : 'Unknown' }}</span>
                        <span><i class="fas fa-hdd mr-1"></i>{{ $sizeLabel }}</span>
                        <span><i class="fas fa-file mr-1"></i>{{ $result->totalpart ?? 0 }} files</span>
                        <span><i class="fas fa-download mr-1" aria-hidden="true"></i>{{ $result->grabs ?? 0 }} grabs</span>
                        <span><i class="fas fa-comment mr-1" aria-hidden="true"></i>{{ $result->comments ?? 0 }} comments</span>
                    </div>
                    <x-release-row-actions :result="$result" />
                </div>
            </div>
        </article>
    @endforeach
</div>
