@extends('layouts.main')

@section('content')
@if(!$invite_mode)
<div class="container-fluid px-4 py-3">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="alert alert-warning shadow-sm">
                <h5 class="mb-2"><i class="fa fa-ban me-2"></i>Invitations Disabled</h5>
                <p class="mb-0">User invitations are currently disabled on this site. If you believe this is an error, please contact an administrator.</p>
            </div>
        </div>
    </div>
</div>
@else
<div class="header">
    <div class="breadcrumb-wrapper">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ $site->home_link }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ url('/profile') }}">Profile</a></li>
                <li class="breadcrumb-item active">My Invitations</li>
            </ol>
        </nav>
    </div>
</div>

<div class="container-fluid px-4 py-3">
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fa fa-envelope me-2"></i>My Invitations</h5>
                    <a href="{{ url('/invitations/create') }}" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Send New Invitation
                    </a>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h4 class="mb-1">{{ $stats['total'] ?? 0 }}</h4>
                                    <small>Total Sent</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h4 class="mb-1">{{ $stats['used'] ?? 0 }}</h4>
                                    <small>Accepted</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h4 class="mb-1">{{ $stats['pending'] ?? 0 }}</h4>
                                    <small>Pending</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center">
                                    <h4 class="mb-1">{{ $stats['expired'] ?? 0 }}</h4>
                                    <small>Expired</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Tabs -->
                    <ul class="nav nav-tabs mb-3">
                        <li class="nav-item">
                            <a class="nav-link @if(empty($status)) active @endif" href="{{ url('/invitations') }}">
                                <i class="fa fa-list me-1"></i>All
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link @if(($status ?? '') == 'pending') active @endif" href="{{ url('/invitations?status=pending') }}">
                                <i class="fa fa-clock-o me-1"></i>Pending
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link @if(($status ?? '') == 'used') active @endif" href="{{ url('/invitations?status=used') }}">
                                <i class="fa fa-check me-1"></i>Accepted
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link @if(($status ?? '') == 'expired') active @endif" href="{{ url('/invitations?status=expired') }}">
                                <i class="fa fa-times me-1"></i>Expired
                            </a>
                        </li>
                    </ul>

                    <!-- Invitations Table -->
                    @if(count($invitations) > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th><i class="fa fa-envelope me-1"></i>Email</th>
                                        <th><i class="fa fa-info-circle me-1"></i>Status</th>
                                        <th><i class="fa fa-calendar me-1"></i>Sent Date</th>
                                        <th><i class="fa fa-clock-o me-1"></i>Expires</th>
                                        <th><i class="fa fa-user me-1"></i>Used By</th>
                                        <th><i class="fa fa-check me-1"></i>Used Date</th>
                                        <th><i class="fa fa-cogs me-1"></i>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($invitations as $invitation)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <i class="fa fa-envelope text-muted me-2"></i>
                                                    {{ $invitation['email'] }}
                                                </div>
                                            </td>
                                            <td>
                                                @if($invitation['used_at'])
                                                    <span class="badge bg-success"><i class="fa fa-check me-1"></i>Accepted</span>
                                                @elseif($invitation['expires_at'] < time())
                                                    <span class="badge bg-danger"><i class="fa fa-times me-1"></i>Expired</span>
                                                @elseif(!$invitation['is_active'])
                                                    <span class="badge bg-secondary"><i class="fa fa-ban me-1"></i>Cancelled</span>
                                                @else
                                                    <span class="badge bg-warning"><i class="fa fa-clock-o me-1"></i>Pending</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <i class="fa fa-calendar text-muted me-2"></i>
                                                    {{ date('M j, Y', $invitation['created_at']) }}
                                                    <small class="text-muted ms-1">{{ date('H:i', $invitation['created_at']) }}</small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <i class="fa fa-clock-o text-muted me-2"></i>
                                                    {{ date('M j, Y', $invitation['expires_at']) }}
                                                    <small class="text-muted ms-1">{{ date('H:i', $invitation['expires_at']) }}</small>
                                                </div>
                                            </td>
                                            <td>
                                                @if(isset($invitation['used_by_user']))
                                                    <div class="d-flex align-items-center">
                                                        <i class="fa fa-user text-muted me-2"></i>
                                                        {{ $invitation['used_by_user']['username'] ?? $invitation['used_by_user']['email'] }}
                                                    </div>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($invitation['used_at'])
                                                    <div class="d-flex align-items-center">
                                                        <i class="fa fa-check text-muted me-2"></i>
                                                        {{ date('M j, Y', $invitation['used_at']) }}
                                                        <small class="text-muted ms-1">{{ date('H:i', $invitation['used_at']) }}</small>
                                                    </div>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if(!$invitation['used_at'] && $invitation['expires_at'] > time() && $invitation['is_active'])
                                                    <div class="btn-group" role="group">
                                                        <form method="POST" action="{{ url('/invitations/' . $invitation['id'] . '/resend') }}" class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-outline-primary" title="Resend Invitation">
                                                                <i class="fas fa-paper-plane"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="{{ url('/invitations/' . $invitation['id']) }}" class="d-inline">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel Invitation"
                                                                    onclick="return confirm('Are you sure you want to cancel this invitation?')">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if(isset($pagination_links))
                            <div class="mt-4">
                                {!! $pagination_links !!}
                            </div>
                        @endif
                    @else
                        <div class="alert alert-info text-center">
                            <i class="fa fa-info-circle fa-2x mb-3"></i>
                            <h5>No Invitations Found</h5>
                            <p class="mb-3">You haven't sent any invitations yet.</p>
                            <a href="{{ url('/invitations/create') }}" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i> Send Your First Invitation
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

