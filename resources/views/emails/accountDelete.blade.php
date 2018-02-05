@extends('emails.email_layout')

@section('title')
    User Account deleted
@endsection

@section('content')
    User <b>{{ $username }}</b> has deleted his/hers account from {{ $site }}.
@endsection
