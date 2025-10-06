@extends('layouts.main')

@section('content')
<div class="container-fluid px-4 py-3">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ $site->home_link }}">Home</a></li>
            <li class="breadcrumb-item active">Invitation</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fa fa-envelope-open me-2"></i>Invitation to Join {{ $site->title }}</h5>
                </div>
                <div class="card-body">
                    @if($preview)
                        <div class="alert alert-info border-0 mb-4">
                            <div class="d-flex">
                                <i class="fa fa-user-plus fa-2x me-3 mt-1"></i>
                                <div>
                                    <h6 class="alert-heading mb-2">You've been invited!</h6>
                                    <p class="mb-0">
                                        <strong>{{ $preview['inviter_name'] ?? 'Someone' }}</strong> has invited you to join <strong>{{ $site->title }}</strong>.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="card border-0 bg-light">
                                    <div class="card-header bg-transparent border-bottom">
                                        <h6 class="mb-0"><i class="fa fa-info-circle me-1"></i>Invitation Details</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row mb-3">
                                            <div class="col-5 text-muted"><i class="fa fa-user me-1"></i>Invited by:</div>
                                            <div class="col-7 fw-medium">{{ $preview['inviter_name'] ?? 'Anonymous' }}</div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-5 text-muted"><i class="fa fa-envelope me-1"></i>Email:</div>
                                            <div class="col-7 fw-medium">{{ $preview['email'] ?? 'N/A' }}</div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-5 text-muted"><i class="fa fa-clock-o me-1"></i>Expires:</div>
                                            <div class="col-7">
                                                @if(isset($preview['expires_at']))
                                                    {{ date('M j, Y H:i', $preview['expires_at']) }}
                                                    @if($preview['expires_at'] < time())
                                                        <br><span class="badge bg-danger mt-1"><i class="fa fa-times me-1"></i>Expired</span>
                                                    @elseif($preview['is_used'])
                                                        <br><span class="badge bg-success mt-1"><i class="fa fa-check me-1"></i>Already Used</span>
                                                    @else
                                                        <br><span class="badge bg-success mt-1"><i class="fa fa-check me-1"></i>Valid</span>
                                                    @endif
                                                @else
                                                    N/A
                                                @endif
                                            </div>
                                        </div>
                                        @if(isset($preview['metadata']['role']) && isset($preview['role_name']))
                                            <div class="row">
                                                <div class="col-5 text-muted"><i class="fa fa-user-tag me-1"></i>Assigned Role:</div>
                                                <div class="col-7 fw-medium">
                                                    <span class="badge bg-primary">{{ $preview['role_name'] ?? 'Default' }}</span>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="card border-0 bg-light">
                                    <div class="card-header bg-transparent border-bottom">
                                        <h6 class="mb-0"><i class="fa fa-list-ol me-1"></i>What's Next?</h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-3">To accept this invitation and create your account:</p>
                                        <ol class="mb-0">
                                            <li class="mb-2">Click the "Accept Invitation" button below</li>
                                            <li class="mb-2">Fill out the registration form with your details</li>
                                            <li class="mb-2">Verify your email address when prompted</li>
                                            <li class="mb-0">Start exploring {{ $site->title }}!</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            @if($preview['is_used'])
                                <div class="alert alert-success border-0 mb-4">
                                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                                    <h6>This invitation has already been used</h6>
                                    <p class="mb-0">The account has been successfully created using this invitation.</p>
                                </div>
                                <a href="{{ url('/login') }}" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i> Login to Your Account
                                </a>
                            @elseif($preview['expires_at'] < time())
                                <div class="alert alert-danger border-0 mb-4">
                                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                                    <h6>This invitation has expired</h6>
                                    <p class="mb-0">Please contact the person who invited you for a new invitation.</p>
                                </div>
                                <a href="{{ url('/contact') }}" class="btn btn-outline-primary">
                                    <i class="fa fa-envelope me-1"></i> Contact Support
                                </a>
                            @else
                                <a href="{{ url('/register?invitation=' . $token) }}" class="btn btn-primary btn-lg shadow">
                                    <i class="fas fa-user-plus me-2"></i> Accept Invitation & Create Account
                                </a>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        Already have an account? <a href="{{ url('/login') }}" class="text-decoration-none">Login here</a>
                                    </small>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="alert alert-danger border-0 text-center">
                            <i class="fas fa-exclamation-triangle fa-3x mb-3 text-danger"></i>
                            <h5 class="text-danger">Invalid Invitation</h5>
                            <p class="mb-4">This invitation link is not valid, has expired, or has been removed.</p>
                            <div class="d-flex gap-2 justify-content-center">
                                <a href="{{ url('/contact') }}" class="btn btn-outline-primary">
                                    <i class="fa fa-envelope me-1"></i> Contact Support
                                </a>
                                <a href="{{ url('/register') }}" class="btn btn-primary">
                                    <i class="fa fa-user-plus me-1"></i> Register Without Invitation
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

