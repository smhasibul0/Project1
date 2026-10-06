@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Add {{ \App\Models\Lc::types()[$type] }}</h4>
                <small class="text-muted">Pay a purchase invoice by {{ strtolower(\App\Models\Lc::typeNames()[$type]) }}</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('lc.index', $type) }}">{{ \App\Models\Lc::types()[$type] }}</a></li>
                    <li class="breadcrumb-item active">Add</li>
                </ol>
            </div>
        </div>

        <form action="{{ route('lc.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @include('admin.backend.lc._form', ['lc' => null])
        </form>
    </div>
</div>
@endsection
