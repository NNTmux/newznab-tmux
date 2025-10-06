<div class="card card-default shadow-sm mb-4">
    <div class="card-header bg-light">
        <div class="d-flex justify-content-between align-items-center">
            <h3 class="mb-0"><i class="fa fa-tv me-2 text-primary"></i>My TV Shows</h3>
            <div class="breadcrumb-wrapper">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 py-0">
                        <li class="breadcrumb-item"><a href="{{ url($site->home_link) }}">Home</a></li>
                        <li class="breadcrumb-item active">My TV Shows</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="card-body">
        <div class="alert alert-info mb-4">
            <i class="fa fa-info-circle me-2"></i>
            Using 'My TV Shows' you can bookmark your favorite series. When new episodes become available, they will be added to your
            <strong><a href="{{ url("/rss/myshows?dl=1&i={$userdata->id}&api_token={$userdata->api_token}") }}" class="alert-link">
                <i class="fa fa-rss me-1"></i>RSS Feed
            </a></strong>
            which you can use to automatically download new episodes.
        </div>

        <div class="d-flex flex-wrap gap-2 mb-4">
            <a class="btn btn-primary" href="{{ route('series') }}" data-bs-toggle="tooltip" data-bs-placement="top" title="View available TV series">
                <i class="fa fa-list me-2"></i>View All Series
            </a>
            <a class="btn btn-info" href="{{ url('/myshows/browse') }}" data-bs-toggle="tooltip" data-bs-placement="top" title="View a list of all releases in your shows">
                <i class="fa fa-search me-2"></i>View Releases
            </a>
            <a class="btn btn-outline-secondary" href="{{ url("/rss/myshows?dl=1&i={$userdata->id}&api_token={$userdata->api_token}") }}" data-bs-toggle="tooltip" data-bs-placement="top" title="All releases in your shows as an RSS feed">
                <i class="fa fa-rss me-2"></i>RSS Feed
            </a>
        </div>

        @if(count($shows ?? []) > 0)
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th width="120">Category</th>
                            <th width="110">Added</th>
                            <th width="130" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($shows as $show)
                            <tr>
                                <td>
                                    <a class="text-decoration-none fw-medium" title="View series details"
                                       href="{{ url("/series/{$show['videos_id']}") }}" data-bs-toggle="tooltip" data-bs-placement="top">
                                        {{ e(wordwrap($show['title'] ?? '', 75, "\n", true)) }}
                                    </a>
                                </td>
                                <td>
                                    <span class="badge bg-secondary rounded-pill">
                                        <i class="fa fa-folder-open me-1"></i>{{ !empty($show['categoryNames']) ? e($show['categoryNames']) : 'All' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center" title="Added on {{ $show['created_at'] ?? '' }}">
                                        <i class="fa fa-calendar text-muted me-2"></i>
                                        {{ isset($show['created_at']) ? date('M d, Y', strtotime($show['created_at'])) : '' }}
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <a class="btn btn-sm btn-warning myshows"
                                           href="{{ url("/myshows?action=edit&id={$show['videos_id']}") }}"
                                           rel="edit" name="series{{ $show['videos_id'] }}"
                                           data-bs-toggle="tooltip" data-bs-placement="top" title="Edit Categories">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                        <a class="btn btn-sm btn-danger confirm_action myshows"
                                           href="{{ url("/myshows?action=delete&id={$show['videos_id']}") }}"
                                           rel="remove" name="series{{ $show['videos_id'] }}"
                                           data-bs-toggle="tooltip" data-bs-placement="top" title="Remove from My Shows">
                                            <i class="fa fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="alert alert-info">
                <i class="fa fa-info-circle me-2"></i>No TV shows bookmarked yet. Browse the <a href="{{ route('series') }}" class="alert-link">Series List</a> to add shows to your collection.
            </div>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

    // Confirm before removing shows
    document.querySelectorAll('.confirm_action').forEach(element => {
        element.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to remove this show from your list?')) {
                e.preventDefault();
            }
        });
    });
});
</script>

