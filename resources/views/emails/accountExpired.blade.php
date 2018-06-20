@extends('emails.email_layout')

@section('title')
    Your account has expired and account level has been downgraded
@endsection

@section('content')
    Dear {{ $username }},
    <br>
    Your account has expired and has been downgraded to {{ $account }}
    <br><br><br>
    Greetings from {{ $site }}
@endsection
