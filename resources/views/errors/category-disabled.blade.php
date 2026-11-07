@php
    $categoryKind = ($isSubcategory ?? false) ? __('Subcategory') : __('Category');
@endphp
@extends('errors.layout', ['actions' => [['url' => url('profileedit'), 'label' => __('Go to profile settings'), 'icon' => 'fa-sliders']]])

@section('title', __(':kind Disabled', ['kind' => $categoryKind]))
@section('icon', 'fa-eye-slash')
@section('message')
    <span class="text-link">{{ $category }}</span> {{ __(':kind disabled', ['kind' => strtolower($categoryKind)]) }}
@endsection
@section('description')
    @if($isSubcategory ?? false)
        {{ __('You have excluded this subcategory in your profile settings. Remove it from your exclusions to see its releases.') }}
    @else
        {{ __('You have hidden this category in your profile settings. Enable it again to see its releases.') }}
    @endif
@endsection
