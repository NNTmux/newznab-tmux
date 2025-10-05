@extends('layouts.admin')

@section('title', $title ?? 'Release Naming Regex Test')

@section('content')
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">{{ $title }}</h4>
            </div>
            <div>
                <a href="{{ url('/admin/release_naming_regexes-list') }}" class="btn btn-sm btn-outline-primary">
                    <i class="fa fa-arrow-left me-2"></i>Back to List
                </a>
            </div>
        </div>
    </div>

    <div class="card-body">
        <div class="alert alert-info mb-4">
            <i class="fa fa-info-circle me-2"></i>Test your release naming regex patterns against actual release data from your database.
        </div>

        <form method="GET" action="{{ url('/admin/release_naming_regexes-test') }}" class="mb-4">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="group" class="form-label fw-bold">Group:</label>
                    <input type="text" id="group" name="group" class="form-control" value="{{ $group }}" placeholder="alt.binaries.teevee" required>
                    <small class="text-muted">Enter a newsgroup name to test against</small>
                </div>
                <div class="col-md-3">
                    <label for="showlimit" class="form-label fw-bold">Show Limit:</label>
                    <input type="number" id="showlimit" name="showlimit" class="form-control" value="{{ $showlimit }}" min="1" max="1000">
                    <small class="text-muted">Results to display</small>
                </div>
                <div class="col-md-3">
                    <label for="querylimit" class="form-label fw-bold">Query Limit:</label>
                    <input type="number" id="querylimit" name="querylimit" class="form-control" value="{{ $querylimit }}" min="1" max="500000">
                    <small class="text-muted">Max releases to query</small>
                </div>
            </div>

            <div class="mb-3">
                <label for="regex" class="form-label fw-bold">Regex:</label>
                <textarea id="regex" name="regex" class="form-control" rows="4" required placeholder="/^(?P<name>.*?)([\. ]S\d{1,3}[\. ]?E\d{1,3})/i">{{ $regex }}</textarea>
                <small class="text-muted">Enter the regex pattern to test. Include delimiters and flags.</small>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fa fa-play me-2"></i>Test Regex
            </button>
        </form>

        @if($data)
            <hr class="my-4">
            <h5 class="mb-3">Test Results:</h5>

            @if(count($data) > 0)
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover">
                        <thead>
                            <tr>
                                <th style="width: 80px">Release ID</th>
                                <th>Original Name</th>
                                <th>New Name</th>
                                <th style="width: 100px">Match</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data as $row)
                                <tr>
                                    <td>{{ $row['releaseID'] ?? $row['id'] ?? 'N/A' }}</td>
                                    <td><small>{{ \Illuminate\Support\Str::limit($row['oldName'] ?? $row['name'] ?? '', 80) }}</small></td>
                                    <td>
                                        @if(isset($row['newName']) && $row['newName'])
                                            <small class="text-success fw-bold">{{ \Illuminate\Support\Str::limit($row['newName'], 80) }}</small>
                                        @else
                                            <small class="text-muted">—</small>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if(isset($row['match']) && $row['match'])
                                            <span class="badge bg-success"><i class="fa fa-check me-1"></i>Match</span>
                                        @else
                                            <span class="badge bg-danger"><i class="fa fa-times me-1"></i>No Match</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-success mt-3">
                    <i class="fa fa-check-circle me-2"></i>
                    Tested {{ count($data) }} releases. Review the matches above.
                </div>
            @else
                <div class="alert alert-warning">
                    <i class="fa fa-exclamation-triangle me-2"></i>No releases found for the specified group or no matches found.
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
