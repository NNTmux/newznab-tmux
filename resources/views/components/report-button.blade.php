@blaze
{{-- Report button trigger - the modal is rendered once in layouts/main via partials/report-modal --}}
@props([
    'releaseId' => null,
    'releaseGuid' => null,
    'size' => 'sm', // sm, md, lg
    'variant' => 'icon', // icon, button, button-lg, text
])

@auth
@if($variant === 'icon')
    <button type="button"
            data-report-release-id="{{ $releaseId }}"
            class="report-trigger text-red-500 dark:text-red-400 hover:text-red-600 dark:hover:text-red-300 transition text-sm"
            title="Report this release">
        <i class="fas fa-flag"></i>
    </button>
@elseif($variant === 'button-lg')
    <button type="button"
            data-report-release-id="{{ $releaseId }}"
            class="report-trigger px-4 py-2 bg-red-600 dark:bg-red-700 text-white rounded-lg hover:bg-red-700 dark:hover:bg-red-800 transition inline-flex items-center"
            title="Report this release">
        <i class="fas fa-flag mr-2"></i>
        <span class="report-label">Report</span>
    </button>
@elseif($variant === 'button')
    <button type="button"
            data-report-release-id="{{ $releaseId }}"
            class="report-trigger px-3 py-1.5 text-{{ $size }} bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-red-100 dark:hover:bg-red-900 hover:text-red-700 dark:hover:text-red-300 transition inline-flex items-center">
        <i class="fas fa-flag mr-1.5"></i>
        <span class="report-label">Report</span>
    </button>
@else
    <button type="button"
            data-report-release-id="{{ $releaseId }}"
            class="report-trigger text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 text-{{ $size }} transition">
        <i class="fas fa-flag mr-1"></i>
        <span class="report-label">Report Issue</span>
    </button>
@endif
@endauth

