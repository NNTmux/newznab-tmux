@extends('errors.layout')

@section('title')
    HTTP/1.1 403 Unathorized
@endsection

@section('content')
    {{ config('app.name') }} <br><br><br>
    <b>You are not logged in!</b> <br>Please <a href='{!! url('/login'); !!}'>login</a>
@endsection
