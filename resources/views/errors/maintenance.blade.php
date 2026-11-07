@extends('errors.layout')

@section('title', __('Maintenance'))
@section('code', '503')
@section('icon', 'fa-screwdriver-wrench')
@section('message', __('Down for maintenance'))
@section('description', __('We are performing maintenance tasks, please try again later.'))
