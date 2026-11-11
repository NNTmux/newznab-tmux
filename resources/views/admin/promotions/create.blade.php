@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header title="Create New Promotion" icon="fas fa-plus" />
    <x-admin.card class="max-w-3xl mx-auto">
        @include('admin.promotions.partials.form', ['promotion' => null])
    </x-admin.card>
</div>
@endsection
