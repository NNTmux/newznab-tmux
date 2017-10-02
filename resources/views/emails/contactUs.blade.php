@extends('emails.email_layout')

@section('title')
    Contact form submitted
@endsection

@section('content')
    {{ $mailBody }}
@endsection
