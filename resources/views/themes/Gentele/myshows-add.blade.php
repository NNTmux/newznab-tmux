<div class="card card-default shadow-sm mb-4">
    <div class="card-header bg-light">
        <div class="d-flex justify-content-between align-items-center">
            <h3 class="mb-0"><i class="fa fa-tv me-2 text-primary"></i>{{ ucfirst($type ?? 'add') }} Show to Watchlist</h3>
            <div class="breadcrumb-wrapper">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 py-0">
                        <li class="breadcrumb-item"><a href="{{ url($site->home_link) }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ url('/myshows') }}">My Shows</a></li>
                        <li class="breadcrumb-item active">{{ ucfirst($type ?? 'add') }} Show</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="card-body">
        <div class="mb-4">
            <div class="d-flex align-items-center gap-3 mb-3">
                <img class="rounded shadow-sm" style="max-width:100px"
                     src="{{ url("/covers/tvshows/{$video}_thumb.jpg") }}"
                     onerror="this.src='{{ url('/covers/tvshows/no-cover.jpg') }}'"
                     alt="{{ e($show['title'] ?? '') }}" />

                <div>
                    <h4 class="mb-1">{{ ucfirst($type ?? 'add') }} "{{ e($show['title'] ?? '') }}" to watchlist</h4>
                    <p class="text-muted mb-0">Select categories below to organize this show in your collection.</p>
                </div>
            </div>

            <div class="alert alert-info">
                <i class="fa fa-info-circle me-2"></i>
                Adding shows to your watchlist will notify you through your
                <a href="{{ url("/rss/myshows?dl=1&i={$userdata->id}&api_token={$userdata->api_token}") }}" class="alert-link">
                    <i class="fa fa-rss me-1"></i>RSS Feed
                </a>
                when new episodes become available.
            </div>
        </div>

        {!! Form::open(['id' => 'myshows', 'class' => 'form', 'url' => "myshows?action=do{$type}"]) !!}
            <input type="hidden" name="id" value="{{ $video }}"/>
            @if(!empty($from))
                <input type="hidden" name="from" value="{{ $from }}" />
            @endif

            <div class="mb-4">
                <label class="form-label fw-bold mb-3">Choose Categories:</label>
                <div class="d-flex flex-wrap gap-3" id="category-container">
                    @foreach($cat_ids ?? [] as $index => $cat_id)
                        <div class="category-checkbox">
                            <input type="checkbox"
                                   id="category_{{ $cat_id }}"
                                   name="category[]"
                                   value="{{ $cat_id }}"
                                   @if(in_array($cat_id, $cat_selected ?? [])) checked @endif>
                            <label for="category_{{ $cat_id }}">{{ $cat_names[$cat_id] ?? '' }}</label>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit" name="{{ $type ?? 'add' }}">
                    <i class="fa {{ ($type ?? 'add') == 'add' ? 'fa-plus' : 'fa-edit' }} me-2"></i>{{ ucfirst($type ?? 'add') }} Show
                </button>
                <a href="{{ url('/myshows') }}" class="btn btn-outline-secondary">
                    <i class="fa fa-arrow-left me-2"></i>Back to My Shows
                </a>
            </div>
        {!! Form::close() !!}
    </div>
</div>

<style>
/* Style for checkboxes to make them more modern */
.category-checkbox {
    display: inline-flex;
    align-items: center;
}

.category-checkbox label {
    display: flex;
    align-items: center;
    padding: 8px 15px;
    margin-bottom: 0;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.category-checkbox label:hover {
    background-color: #e9ecef;
}

.category-checkbox input[type="checkbox"] {
    margin-right: 8px;
    cursor: pointer;
}

.category-checkbox input[type="checkbox"]:checked ~ label,
.category-checkbox input[type="checkbox"]:checked + label {
    background-color: #d1ecf1;
    border-color: #4dabf7;
}
</style>

