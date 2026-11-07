@extends('errors.layout', ['actions' => [['url' => url('login'), 'label' => __('Sign in'), 'icon' => 'fa-right-to-bracket']]])

@section('title', __('Unauthorized'))
@section('code', '401')
@section('icon', 'fa-lock')
@section('message', __('Sign in required'))
@section('description', __('You need to be signed in to view this page.'))
