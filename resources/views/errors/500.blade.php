@extends('errors.layout')

@section('title', __('Server Error'))
@section('code', '500')
@section('icon', 'fa-triangle-exclamation')
@section('message', __('Something went wrong'))
@section('description', __('An unexpected error occurred on our side. Please try again in a moment.'))
