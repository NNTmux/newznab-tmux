<x-page-header :title="(($type ?? 'add') === 'edit' ? 'Edit' : 'Add').' TV show'" eyebrow="Series watchlist" description="Choose how this show should be organized in your followed collection." icon="fas fa-tv">
    <x-slot:actions>
        <a href="{{ url('/myshows') }}" class="workspace-hero__action"><i class="fas fa-arrow-left" aria-hidden="true"></i>My shows</a>
    </x-slot:actions>
</x-page-header>

@include('partials.watchlist-category-form', [
    'action' => url('myshows?action=do'.($type ?? 'add')),
    'hidden' => ['id' => $video],
    'itemTitle' => (string) ($show['title'] ?? ''),
    'kind' => 'show',
    'coverUrl' => getImageAssetUrl('tvshows', $video.'_thumb', url('/covers/tvshows/no-cover.jpg')),
    'fallbackUrl' => url('/covers/tvshows/no-cover.jpg'),
    'rssUrl' => url("/rss/myshows?dl=1&i={$userdata->id}&api_token={$userdata->api_token}"),
    'backUrl' => url('/myshows'),
    'backLabel' => 'Back to My shows',
])
