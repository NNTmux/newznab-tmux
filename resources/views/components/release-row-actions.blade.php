@props(['result', 'compact' => false])

@php
    $reportedCount = (int) ($result->total_report_count ?? $result->report_count ?? 0);
@endphp

<div @class(['release-row-actions', 'release-row-actions--compact' => $compact])>
    <a href="{{ url('/getnzb/' . $result->guid) }}" class="download-nzb release-row-actions__download" title="Download NZB">
        <i class="fa fa-download" aria-hidden="true"></i>
        <span @class(['sr-only' => $compact])>Download</span>
    </a>
    <a href="{{ url('/details/' . $result->guid) }}" class="release-row-actions__details" title="View release details">
        <i class="fa fa-info-circle" aria-hidden="true"></i>
        <span @class(['sr-only' => $compact])>Details</span>
    </a>
    <button type="button" class="add-to-cart release-row-actions__basket" data-guid="{{ $result->guid }}" title="Add to basket">
        <i class="icon_cart fa fa-shopping-basket" aria-hidden="true"></i>
        <span @class(['sr-only' => $compact])>Add to basket</span>
    </button>
    @if(!empty($result->imdbid) && imdb_id_is_valid($result->imdbid))
        <a href="{{ url('/mymovies?id=add&imdb=' . $result->imdbid) }}" class="release-row-actions__movies" title="Add to My Movies">
            <i class="fa fa-film" aria-hidden="true"></i>
            <span @class(['sr-only' => $compact])>My Movies</span>
        </a>
    @endif
    <x-report-button :release-id="$result->id" :reported-count="$reportedCount" :variant="$compact ? 'icon' : 'button'" />
</div>
