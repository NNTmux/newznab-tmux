@extends('errors.layout')

@section('title', __('Too Many Requests'))
@section('code', '429')
@section('icon', 'fa-gauge-high')
@section('message', __('Too many requests'))
@section('description', __('You are sending requests faster than allowed. Slow down and try again shortly.'))
