@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-plus" />
    <x-admin.card>
        <div class="px-6 py-6">
            @include('admin.status.partials.form', ['incident' => null])
        </div>
    </x-admin.card>
</div>
@endsection
