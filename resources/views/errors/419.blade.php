@extends('errors.layout')

@section('title', __('Page Expired'))
@section('code', '419')
@section('icon', 'fa-clock-rotate-left')
@section('message', __('Page expired'))
@section('description', __('Your session timed out. Go back, refresh the page and try again.'))
