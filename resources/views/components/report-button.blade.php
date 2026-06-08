@blaze
{{-- Report button trigger - the modal is rendered once in layouts/main via partials/report-modal --}}
@props([
    'releaseId' => null,
    'releaseGuid' => null,
    'size' => 'sm', // sm, md, lg
    'variant' => 'icon', // icon, button, button-lg, text
    'reportedCount' => 0,
])

@php
    $reportedCount = (int) $reportedCount;
    $hasReports = $reportedCount > 0;
    $reportTitle = $hasReports
        ? 'This release has already been reported. Click to submit another report.'
        : 'Report this release';
@endphp

@auth
@if($variant === 'icon')
    <button type="button"
            data-report-release-id="{{ $releaseId }}"
            class="report-trigger {{ $hasReports ? 'text-orange-500 dark:text-orange-400 hover:text-orange-600 dark:hover:text-orange-300' : 'text-red-500 dark:text-red-400 hover:text-red-600 dark:hover:text-red-300' }} transition text-sm"
            title="{{ $reportTitle }}">
        <i class="fas fa-flag"></i>
    </button>
@elseif($variant === 'button-lg')
    <button type="button"
            data-report-release-id="{{ $releaseId }}"
            class="report-trigger px-4 py-2 {{ $hasReports ? 'bg-orange-600 dark:bg-orange-700 hover:bg-orange-700 dark:hover:bg-orange-800' : 'bg-red-600 dark:bg-red-700 hover:bg-red-700 dark:hover:bg-red-800' }} text-white rounded-lg transition inline-flex items-center"
            title="{{ $reportTitle }}">
        <i class="fas fa-flag mr-2"></i>
        <span class="report-label">{{ $hasReports ? 'Reported' : 'Report' }}</span>
    </button>
@elseif($variant === 'button')
    <button type="button"
            data-report-release-id="{{ $releaseId }}"
            class="report-trigger px-3 py-1.5 text-{{ $size }} {{ $hasReports ? 'bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-200 hover:bg-orange-200 dark:hover:bg-orange-800' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-red-100 dark:hover:bg-red-900 hover:text-red-700 dark:hover:text-red-300' }} rounded-lg transition inline-flex items-center"
            title="{{ $reportTitle }}">
        <i class="fas fa-flag mr-1.5"></i>
        <span class="report-label">{{ $hasReports ? 'Reported' : 'Report' }}</span>
    </button>
@else
    <button type="button"
            data-report-release-id="{{ $releaseId }}"
            class="report-trigger {{ $hasReports ? 'text-orange-600 dark:text-orange-400 hover:text-orange-700 dark:hover:text-orange-300' : 'text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400' }} text-{{ $size }} transition"
            title="{{ $reportTitle }}">
        <i class="fas fa-flag mr-1"></i>
        <span class="report-label">{{ $hasReports ? 'Reported' : 'Report Issue' }}</span>
    </button>
@endif
@endauth

