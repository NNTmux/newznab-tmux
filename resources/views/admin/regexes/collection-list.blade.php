@extends('layouts.admin')

@section('content')
@include('admin.regexes.partials.list', ['type' => 'collection'])
@endsection
