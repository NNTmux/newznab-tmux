@extends('layouts.main')

@push('modals')
    @include('partials.release-modals')
@endpush

@section('content')
<div class="release-detail-page surface-panel rounded-xl shadow-sm p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200 mb-2">Release Details</h1>
        <nav class="text-sm text-gray-600 dark:text-gray-400">
            <a href="{{ url('/') }}" class="hover:text-primary-600 dark:hover:text-primary-400">Home</a>
            <i class="fas fa-chevron-right mx-2 text-xs"></i>
            <span class="wrap-break-word break-all">{{ $release->searchname }}</span>
        </nav>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
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
