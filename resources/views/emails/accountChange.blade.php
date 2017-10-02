@extends('emails.email_layout')

@section('title')
    Your account level has been changed
@endsection

@section('content')
    Dear {{ $username }},
    <br>
    Your account has been changed to {{ $account }}
    <br><br><br>
    Greetings from {{ $site }}
@endsection
