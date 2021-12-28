@extends('errors.layout')

@section('title')
    HTTP/1.1 401 Unathorized
@endsection

@section('content')
    {{ config('app.name') }} <br><br><br>
    <b>Acess denied! You're permissions and/or role do not allow you to access this page</b>
@endsection
