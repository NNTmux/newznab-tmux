@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header title="Edit Promotion" icon="fas fa-edit" />
    <x-admin.card class="max-w-3xl mx-auto">
        @include('admin.promotions.partials.form', ['promotion' => $promotion])
    </x-admin.card>
</div>
@endsection
