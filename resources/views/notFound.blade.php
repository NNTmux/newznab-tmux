@extends ('errors.layout')

@section ('content')
    <h3>Sorry {{ auth()->user()->username }} ! This page doesn't exist.</h3>
    <br>
    <h4>Browse back and try again.</h4>
@stop
