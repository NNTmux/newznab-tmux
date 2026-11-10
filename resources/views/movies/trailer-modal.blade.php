<!-- Modal View for Trailer - No layout wrapper -->
<div class="p-4">
    @if(isset($movie))
        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-4">{{ $movie['title'] ?? 'Movie Trailer' }}</h2>

        @if(!empty($movie['trailer_url'] ?? null))
            <div class="mb-4">
                <a href="{{ $site->dereferrerLink ?? '' }}{{ $movie['trailer_url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center px-4 py-2 bg-accent text-white rounded-lg hover:bg-accent-hover transition">
                    <i aria-hidden="true" class="fas fa-circle-play mr-2"></i> Watch Trailer
                </a>
            </div>
        @else
            <div class="bg-gray-100 dark:bg-gray-800 p-8 rounded-lg text-center">
                <i aria-hidden="true" class="fas fa-video-slash text-gray-400 text-5xl mb-4"></i>
                <p class="text-gray-600">No trailer available for this movie.</p>
            </div>
        @endif

        @if(isset($movie['plot']) && $movie['plot'])
            <div class="mt-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Plot</h3>
                <p class="text-gray-700 dark:text-gray-300">{{ $movie['plot'] }}</p>
            </div>
        @endif
    @endif
</div>

