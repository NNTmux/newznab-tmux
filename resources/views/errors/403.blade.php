@extends('errors.layout')

@section('title', __('Forbidden'))
@section('code', '403')
@section('icon', 'fa-ban')
@section('message', __($exception->getMessage() ?: 'Forbidden'))
@section('description', __('You do not have permission to view this page.'))
