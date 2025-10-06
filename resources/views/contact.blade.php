@extends('layouts.main')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h4 class="mb-0">Contact Us</h4>
                <div class="breadcrumb-wrapper mt-2">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 bg-transparent p-0">
                            <li class="breadcrumb-item"><a href="{{ url($site->home_link ?? '/') }}">Home</a></li>
                            <li class="breadcrumb-item active">Contact</li>
                        </ol>
                    </nav>
                </div>
            </div>

            <div class="card-body p-4">
                @if(isset($msg) && $msg != '')
                    <div class="alert alert-success mb-4">
                        <i class="fa fa-check-circle me-2"></i>{!! $msg !!}
                    </div>
                @endif

                @if(session('success'))
                    <div class="alert alert-success mb-4">
                        <i class="fa fa-check-circle me-2"></i>{{ session('success') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger mb-4">
                        <i class="fa fa-exclamation-circle me-2"></i>
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="row mb-4">
                    <div class="col-lg-12">
                        <h3 class="mb-3">Have a question?</h3>
                        <p class="text-muted">Don't hesitate to send us a message. Our team will be happy to help you.</p>
                    </div>
                </div>

                {!! Form::open(['url' => route('contact-us'), 'method' => 'POST']) !!}
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label">Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input id="username" type="text" name="username" value="{{ old('username') }}"
                                       placeholder="Your name" class="form-control @error('username') is-invalid @enderror" required>
                            </div>
                            @error('username')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="useremail" class="form-label">Email <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" id="useremail" name="useremail" value="{{ old('useremail') }}"
                                       placeholder="Your email address" class="form-control @error('useremail') is-invalid @enderror" required>
                            </div>
                            @error('useremail')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="comment" class="form-label">Message <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text align-items-start pt-2"><i class="fas fa-comment"></i></span>
                            <textarea rows="7" name="comment" id="comment"
                                      placeholder="Your message" class="form-control @error('comment') is-invalid @enderror" required>{{ old('comment') }}</textarea>
                        </div>
                        @error('comment')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    @if(config('captcha.enabled') == true && !empty(config('captcha.sitekey')) && !empty(config('captcha.secret')))
                        <div class="mb-3 d-flex justify-content-center">
                            {!! NoCaptcha::display() !!}
                        </div>
                        @error('g-recaptcha-response')
                            <div class="text-danger text-center small mb-3">{{ $message }}</div>
                        @enderror
                    @endif

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-paper-plane me-2"></i>Send Message
                        </button>
                    </div>
                {!! Form::close() !!}
            </div>

            <div class="card-footer bg-light">
                <div class="row">
                    <div class="col-md-12 text-center">
                        <p class="mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            We typically respond to messages within 1-2 business days.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if(config('captcha.enabled') == true && !empty(config('captcha.sitekey')) && !empty(config('captcha.secret')))
    {!! NoCaptcha::renderJs() !!}
@endif
@endsection



