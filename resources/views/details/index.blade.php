@extends('layouts.main')

@push('modals')
    @include('partials.release-modals')
    @include('details.partials.image-modal')
@endpush

@section('content')
@php
    $releasePosted = userDateDiffForHumans($release->postdate ?? null);
    $releaseIntro = trim(($releasePosted !== '' ? 'Posted '.$releasePosted : 'Indexed release').(!empty($release->group_name) ? ' in '.$release->group_name : '').'.');
@endphp
<x-page-header :title="$release->searchname" :eyebrow="$release->category_name ?? 'Release'" :current="$release->category_name ?? 'Release details'" :description="$releaseIntro" icon="fas fa-file-zipper" :compact-title="true" class="mb-6">
    <x-slot:stats>
        <span class="workspace-hero__stat"><i class="fas fa-hard-drive" aria-hidden="true"></i>{{ isset($release->size) ? formatBytes($release->size) : 'Size unavailable' }}</span>
        <span class="workspace-hero__stat"><i class="fas fa-file" aria-hidden="true"></i>{{ number_format((int) ($release->totalpart ?? 0)) }} {{ Str::plural('file', (int) ($release->totalpart ?? 0)) }}</span>
        <span class="workspace-hero__stat"><i class="fas fa-download" aria-hidden="true"></i>{{ number_format((int) ($release->grabs ?? 0)) }} {{ Str::plural('grab', (int) ($release->grabs ?? 0)) }}</span>
    </x-slot:stats>
</x-page-header>

<div class="release-detail-page surface-panel rounded-xl shadow-sm p-4 sm:p-6">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="min-w-0 lg:col-span-2 space-y-6">
            @include('details.partials.cover-actions')

            {{-- Metadata panels sit side by side on wide screens; a lone panel spans the full width. --}}
            <div class="detail-info-grid">
                @include('details.partials.movie-info')
                @include('details.partials.tv-info')
                @include('details.partials.music-info')
                @include('details.partials.game-info')
                @include('details.partials.console-info')
                @include('details.partials.book-info')
                @include('details.partials.anime-info')
                @include('details.partials.password-info')
                @include('details.partials.media-metadata')
                @include('details.partials.predb-info')
            </div>

            @include('details.partials.similar-releases')

            @include('details.partials.comments')
        </div>

        @include('details.partials.info-sidebar')
    </div>
</div>
@endsection

{{-- NFO modal is included globally via layouts.main --}}

@push('scripts')
@endpush
