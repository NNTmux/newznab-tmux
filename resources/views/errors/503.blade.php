@extends('errors.layout')

@section('title')
    Be right back.
@endsection

@section('content')
    @if(!empty($exception->getMessage()))
            {{ $exception->getMessage() }}
    @elseif(App::isDownForMaintenance() && empty($exception->getMessage()))
        We are currently performing scheduled maintenance. We will be back shortly.
    @else
        Service temporarily unavailable. Please try again later!
    @endif
@endsection
