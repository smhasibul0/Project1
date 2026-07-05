@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Add Container</h4>
                <small class="text-muted">Create a container / shipment</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('container.index') }}">Containers</a></li>
                    <li class="breadcrumb-item active">Add</li>
                </ol>
            </div>
        </div>

        <form action="{{ route('container.store') }}" method="POST">
            @csrf
            @include('admin.backend.containers._form', ['container' => null])
        </form>
    </div>
</div>
@endsection
