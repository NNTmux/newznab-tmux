@extends('layouts.main')

@push('modals')
    @include('partials.release-modals')
@endpush

@section('content')
<div class="release-detail-page surface-panel rounded-xl shadow-sm p-4 sm:p-6">
    <x-page-header title="Release details" :current="$release->searchname" eyebrow="Inspect this release" description="Review metadata, previews, files, reports, and download options for this indexed release." icon="fas fa-circle-info" class="mb-6">
        <x-slot:stats>
            <span class="workspace-hero__stat"><i class="fas fa-folder-open" aria-hidden="true"></i>{{ $release->category_name ?? 'Release' }}</span>
            <span class="workspace-hero__stat"><i class="fas fa-database" aria-hidden="true"></i>{{ isset($release->size) ? number_format($release->size / 1073741824, 2).' GB' : 'Size unavailable' }}</span>
        </x-slot:stats>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="min-w-0 lg:col-span-2 space-y-6">
            @include('details.partials.cover-actions')

            @include('details.partials.preview-images')

            @include('details.partials.movie-info')

            @include('details.partials.tv-info')

            @include('details.partials.music-info')

            @include('details.partials.game-info')

            @include('details.partials.console-info')

            @include('details.partials.book-info')

            @include('details.partials.anime-info')

            @include('details.partials.password-info')

            @include('details.partials.media-metadata')

            @include('details.partials.fingerprint-metadata')

            @include('details.partials.predb-info')

            @include('details.partials.comments')
        </div>

        @include('details.partials.info-sidebar')
    </div>
</div>
@endsection

@include('details.partials.image-modal')

{{-- NFO modal is included globally via layouts.main --}}

@push('scripts')
@include('partials.cart-script')
@endpush
