{{-- Movies 2-Column Layout --}}
{{-- Props: $results, $site --}}

<div id="moviesGrid"
     class="grid grid-cols-1 lg:grid-cols-2 gap-4"
     data-user-layout="2">
    @foreach($results as $result)
        @include('movies.partials.movie-card', [
            'result' => $result,
            'layout' => 2,
            'site' => $site,
        ])
    @endforeach
</div>

