@extends('errors.layout')

@section('title', __('Service Unavailable'))
@section('code', '503')
@section('icon', 'fa-plug-circle-exclamation')
@section('message', __('Service unavailable'))
@section('description', __('The site is temporarily unavailable. Please check back soon.'))
