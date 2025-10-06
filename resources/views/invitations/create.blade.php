@extends('layouts.main')

@section('content')
@if(!$invite_mode)
<div class="header">
    <div class="breadcrumb-wrapper">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ $site->home_link }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ url('/profile') }}">Profile</a></li>
                <li class="breadcrumb-item"><a href="{{ url('/invitations') }}">My Invitations</a></li>
                <li class="breadcrumb-item active">Invitations Disabled</li>
            </ol>
        </nav>
    </div>
</div>
<div class="container-fluid px-4 py-3">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="alert alert-warning shadow-sm">
                <h5 class="mb-2"><i class="fa fa-ban me-2"></i>Invitations Disabled</h5>
                <p class="mb-3">User invitations are currently disabled on this site. You cannot send new invitations at this time.</p>
                <a href="{{ url('/profile') }}" class="btn btn-outline-secondary"><i class="fa fa-arrow-left me-1"></i> Back to Profile</a>
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
                <li class="breadcrumb-item"><a href="{{ url('/invitations') }}">My Invitations</a></li>
                <li class="breadcrumb-item active">Send New Invitation</li>
            </ol>
        </nav>
    </div>
</div>

<div class="container-fluid px-4 py-3">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fa fa-paper-plane me-2"></i>Send New Invitation</h5>
                    <div class="d-flex align-items-center gap-3">
                        <div class="badge @if($user_invites_left > 0) bg-success @else bg-danger @endif text-white">
                            <i class="fa fa-envelope me-1"></i>
                            {{ $user_invites_left }} invites left
                        </div>
                        <a href="{{ url('/invitations') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back to Invitations
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fa fa-exclamation-triangle me-2"></i>{{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(!$can_send_invites)
                        <div class="alert alert-warning text-center">
                            <i class="fa fa-exclamation-triangle fa-3x mb-3"></i>
                            <h5>No Invitations Available</h5>
                            <p class="mb-3">You have used all of your available invitations. You cannot send new invitations at this time.</p>
                            <div class="d-flex gap-2 justify-content-center">
                                <a href="{{ url('/invitations') }}" class="btn btn-primary">
                                    <i class="fa fa-arrow-left me-1"></i> Back to My Invitations
                                </a>
                                <a href="{{ url('/contact') }}" class="btn btn-outline-primary">
                                    <i class="fa fa-envelope me-1"></i> Contact Support
                                </a>
                            </div>
                        </div>
                    @else
                        <form method="POST" action="{{ url('/invitations/store') }}">
                            @csrf

                            <div class="alert alert-info mb-4">
                                <div class="d-flex">
                                    <i class="fa fa-info-circle fa-lg me-3 mt-1"></i>
                                    <div>
                                        <strong>Available Invitations:</strong> You have <strong>{{ $user_invites_left }}</strong> invitation{{ $user_invites_left != 1 ? 's' : '' }} remaining.
                                        @if($user_invites_left == 1)
                                            This is your last invitation, so use it wisely!
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="email" class="form-label">
                                    <i class="fa fa-envelope me-1"></i>Email Address <span class="text-danger">*</span>
                                </label>
                                <input type="email"
                                       class="form-control @error('email') is-invalid @enderror"
                                       id="email"
                                       name="email"
                                       value="{{ old('email') }}"
                                       required
                                       placeholder="Enter recipient's email address">
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    <i class="fa fa-info-circle me-1"></i>The person will receive an invitation email at this address.
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="expiry_days" class="form-label">
                                    <i class="fa fa-clock-o me-1"></i>Expiry Period
                                </label>
                                <select class="form-select @error('expiry_days') is-invalid @enderror"
                                        id="expiry_days"
                                        name="expiry_days">
                                    <option value="1" @if(old('expiry_days') == '1') selected @endif>1 Day</option>
                                    <option value="3" @if(old('expiry_days') == '3') selected @endif>3 Days</option>
                                    <option value="7" @if(old('expiry_days') == '7' || !old('expiry_days')) selected @endif>1 Week (Default)</option>
                                    <option value="14" @if(old('expiry_days') == '14') selected @endif>2 Weeks</option>
                                    <option value="30" @if(old('expiry_days') == '30') selected @endif>1 Month</option>
                                </select>
                                @error('expiry_days')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    <i class="fa fa-info-circle me-1"></i>How long the invitation will remain valid.
                                </div>
                            </div>

                            @if(isset($user_roles) && !empty($user_roles))
                                <div class="mb-4">
                                    <label for="role" class="form-label">
                                        <i class="fa fa-user-tag me-1"></i>Default Role <small class="text-muted">(Optional)</small>
                                    </label>
                                    <select class="form-select @error('role') is-invalid @enderror"
                                            id="role"
                                            name="role">
                                        <option value="">Use System Default</option>
                                        @foreach($user_roles as $roleId => $roleName)
                                            <option value="{{ $roleId }}" @if(old('role') == $roleId) selected @endif>
                                                {{ $roleName }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('role')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">
                                        <i class="fa fa-info-circle me-1"></i>The role to assign to the user when they accept the invitation.
                                    </div>
                                </div>
                            @endif

                            <div class="alert alert-info mb-4">
                                <div class="d-flex">
                                    <i class="fa fa-lightbulb-o fa-lg me-3 mt-1"></i>
                                    <div>
                                        <strong>How it works:</strong>
                                        <ul class="mb-0 mt-2">
                                            <li>The recipient will receive an email with a secure invitation link</li>
                                            <li>They can use this link to create their account within the specified time period</li>
                                            <li>Once the time expires, the invitation link becomes invalid</li>
                                            <li>You can track the status of all your invitations from the main invitations page</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="{{ url('/invitations') }}" class="btn btn-outline-secondary me-md-2">
                                    <i class="fa fa-times me-1"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-1"></i> Send Invitation
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

