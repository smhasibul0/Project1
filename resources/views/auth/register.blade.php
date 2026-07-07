@extends('auth.layout')
@section('title', 'Create account')
@section('auth')
<h2>Create your account</h2>
<div class="sub">Get started in a minute.</div>

<form method="POST" action="{{ route('register') }}">
    @csrf

    <div class="row g-2 mb-3">
        <div class="col-4">
            <label for="prefix">Prefix</label>
            <input class="form-control rounded" type="text" name="prefix" id="prefix" value="{{ old('prefix') }}" placeholder="Mr.">
        </div>
        <div class="col-8">
            <label for="first_name">First Name</label>
            <input class="form-control rounded" type="text" name="first_name" id="first_name" value="{{ old('first_name') }}" required placeholder="First name">
            @error('first_name')<span class="field-err">{{ $message }}</span>@enderror
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-6">
            <label for="middle_name">Middle Name</label>
            <input class="form-control rounded" type="text" name="middle_name" id="middle_name" value="{{ old('middle_name') }}" placeholder="Optional">
        </div>
        <div class="col-6">
            <label for="last_name">Last Name</label>
            <input class="form-control rounded" type="text" name="last_name" id="last_name" value="{{ old('last_name') }}" placeholder="Last name">
        </div>
    </div>

    <div class="mb-3">
        <label for="username">Username</label>
        <div class="input-group">
            <span class="input-group-text"><i class="ri-at-line"></i></span>
            <input class="form-control" type="text" name="username" id="username" value="{{ old('username') }}" required placeholder="username">
        </div>
        @error('username')<span class="field-err">{{ $message }}</span>@enderror
    </div>

    <div class="mb-3">
        <label for="email">Email address</label>
        <div class="input-group">
            <span class="input-group-text"><i class="ri-mail-line"></i></span>
            <input class="form-control" type="email" name="email" id="email" value="{{ old('email') }}" required placeholder="you@example.com">
        </div>
        @error('email')<span class="field-err">{{ $message }}</span>@enderror
    </div>

    <div class="row g-2 mb-3">
        <div class="col-6">
            <label for="password">Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="ri-lock-2-line"></i></span>
                <input class="form-control" type="password" name="password" id="password" required placeholder="••••••••">
            </div>
            @error('password')<span class="field-err">{{ $message }}</span>@enderror
        </div>
        <div class="col-6">
            <label for="password_confirmation">Confirm</label>
            <div class="input-group">
                <span class="input-group-text"><i class="ri-lock-2-line"></i></span>
                <input class="form-control" type="password" name="password_confirmation" id="password_confirmation" required placeholder="••••••••">
            </div>
        </div>
    </div>

    <button type="submit" class="btn-accent"><i class="ri-user-add-line me-1"></i> Create Account</button>
</form>

<div class="foot">Already have an account? <a href="{{ route('login') }}">Log in</a></div>
@endsection
