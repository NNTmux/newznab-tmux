@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-edit" />
    <x-admin.card>
        <div class="px-6 py-6">
            @include('admin.status.partials.form', ['incident' => $incident])
        </div>
    </x-admin.card>
</div>
@endsection
