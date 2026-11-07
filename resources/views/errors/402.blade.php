@extends('errors.layout')

@section('title', __('Payment Required'))
@section('code', '402')
@section('icon', 'fa-credit-card')
@section('message', __('Payment required'))
@section('description', __('This feature is not included in your current plan.'))
