@extends('errors.layout', ['actions' => [
    ['url' => url('/'), 'label' => __('Back to home'), 'icon' => 'fa-house'],
    ['url' => url('search'), 'label' => __('Search releases'), 'icon' => 'fa-magnifying-glass'],
]])

@section('title', __('Not Found'))
@section('code', '404')
@section('icon', 'fa-compass')
@section('message', __('Page not found'))
@section('description', __('The page you are looking for does not exist or has been moved.'))
