@extends('emails.email_layout')

@section('title')
    Your account is about to expire
@endsection

@section('content')
    Dear {{ $username }},
    <br>
    Your account role {{ $account }} is about to expire in less than {{ $days }} day(s).
    <br><br><br>
    Greetings from {{ $site }}
@endsection
