@extends('emails.email_layout')

@section('title')
    New user registered
@endsection

@section('content')
    User <b>{{ $username }}</b> has registered a new account on {{ $site }}.
@endsection
