@extends('emails.email_layout')

@section('title')
    You have received an invite
@endsection

@section('content')
    Dear {{ $email }},
    <br>
    you have received an invite from {{ $username }} to register on {{ $site }}
    <br>
    To register click on following link:  <a href="{{$invite}}">{{$invite}}</a><br>
    <br><br><br>
    Greetings from {{ $site }}
@endsection
