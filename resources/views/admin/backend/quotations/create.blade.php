@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Add Quotation</h4>
                <small class="text-muted">Create a customer quotation</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('quotations.index') }}">Quotations</a></li>
                    <li class="breadcrumb-item active">Add</li>
                </ol>
            </div>
        </div>

        <form action="{{ route('quotation.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @include('admin.backend.quotations._form', ['quotation' => null])
        </form>
    </div>
</div>
@endsection
