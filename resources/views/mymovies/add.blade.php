<x-page-header :title="(($type ?? 'add') === 'edit' ? 'Edit' : 'Add').' movie'" eyebrow="Movie watchlist" description="Choose how this movie should be organized in your saved collection." icon="fas fa-film">
    <x-slot:actions>
        <a href="{{ url('/mymovies') }}" class="workspace-hero__action"><i class="fas fa-arrow-left" aria-hidden="true"></i>My movies</a>
    </x-slot:actions>
</x-page-header>

@include('partials.watchlist-category-form', [
    'action' => url('mymovies?id=do'.($type ?? 'add')),
    'hidden' => ['imdb' => $imdbid],
    'itemTitle' => trim(($movie['title'] ?? '').(! empty($movie['year']) ? ' ('.$movie['year'].')' : '')),
    'kind' => 'movie',
    'coverUrl' => getImageAssetUrl('movies', $imdbid.'-cover', url('/covers/movies/no-cover.jpg')),
    'fallbackUrl' => url('/covers/movies/no-cover.jpg'),
    'rssUrl' => url("/rss/mymovies?dl=1&i={$userdata->id}&api_token={$userdata->api_token}"),
    'backUrl' => url('/mymovies'),
    'backLabel' => 'Back to My movies',
])
