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
    $textSize = ['sm' => 'text-sm', 'md' => 'text-base', 'lg' => 'text-lg'][$size] ?? 'text-sm';
    $hasReports = $reportedCount > 0;
    $reportTitle = $hasReports
        ? 'This release has already been reported. Click to submit another report.'
        : 'Report this release';
@endphp

@auth
@if($variant === 'icon')
    <button aria-label="{{ $reportTitle }}" type="button"
            data-report-release-id="{{ $releaseId }}"
            class="report-trigger {{ $hasReports ? 'text-orange-500 dark:text-orange-400 hover:text-orange-600 dark:hover:text-orange-300' : 'text-danger-500 dark:text-danger-400 hover:text-danger-600 dark:hover:text-danger-300' }} transition text-sm"
            title="{{ $reportTitle }}">
        <i aria-hidden="true" class="fas fa-flag"></i>
    </button>
@elseif($variant === 'button-lg')
    <button type="button"
            data-report-release-id="{{ $releaseId }}"
            class="report-trigger release-action px-4 py-2 {{ $hasReports ? 'bg-orange-600 dark:bg-orange-700 hover:bg-orange-700 dark:hover:bg-orange-800' : 'bg-danger-600 dark:bg-danger-700 hover:bg-danger-700 dark:hover:bg-danger-800' }} text-white"
            title="{{ $reportTitle }}">
        <i aria-hidden="true" class="fas fa-flag mr-2"></i>
        <span class="report-label">{{ $hasReports ? 'Reported' : 'Report' }}</span>
    </button>
@elseif($variant === 'button')
    <button type="button"
            data-report-release-id="{{ $releaseId }}"
            class="report-trigger release-action px-3 py-1.5 {{ $textSize }} {{ $hasReports ? 'bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-200 hover:bg-orange-200 dark:hover:bg-orange-800' : 'bg-danger-50 dark:bg-danger-400/10 text-danger-700 dark:text-danger-400 hover:bg-danger-100 dark:hover:bg-danger-400/20 hover:text-danger-800 dark:hover:text-danger-300' }}"
            title="{{ $reportTitle }}">
        <i aria-hidden="true" class="fas fa-flag mr-1.5"></i>
        <span class="report-label">{{ $hasReports ? 'Reported' : 'Report' }}</span>
    </button>
@else
    <button type="button"
            data-report-release-id="{{ $releaseId }}"
            class="report-trigger {{ $hasReports ? 'text-orange-600 dark:text-orange-400 hover:text-orange-700 dark:hover:text-orange-300' : 'text-gray-600 dark:text-gray-400 hover:text-danger-600 dark:hover:text-danger-400' }} {{ $textSize }} transition"
            title="{{ $reportTitle }}">
        <i aria-hidden="true" class="fas fa-flag mr-1"></i>
        <span class="report-label">{{ $hasReports ? 'Reported' : 'Report Issue' }}</span>
    </button>
@endif
@endauth
