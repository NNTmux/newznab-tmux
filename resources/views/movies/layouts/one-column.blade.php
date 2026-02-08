{{-- Movies 1-Column Layout --}}
{{-- Props: $results, $site --}}

<div id="moviesGrid"
     class="grid grid-cols-1 gap-6"
     data-user-layout="1">
    @foreach($results as $result)
        @include('movies.partials.movie-card', [
            'result' => $result,
            'layout' => 1,
            'site' => $site,
        ])
    @endforeach
</div>

