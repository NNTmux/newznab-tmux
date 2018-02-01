@extends('emails.email_layout')

@section('title')
    User Account deleted
@endsection

@section('content')
    {{ $username }} has deleted his/hers account from {{ $site }}.
@endsection
