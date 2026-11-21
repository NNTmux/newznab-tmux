@extends('errors.layout')

@section('title', __('Access blocked'))
@section('code', '403')
@section('icon', 'fa-ban')
@section('message', __('Access from your network address is blocked'))
@section('description', __('This usually follows repeated sign-in attempts for accounts that do not exist. Automatic blocks end on their own after a while. If you think this is a mistake, contact the site staff.'))
