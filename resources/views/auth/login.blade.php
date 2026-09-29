@extends('auth.layout')
@section('title', 'Sign in')
@section('auth')
<h2>Welcome back 👋</h2>
<div class="sub">Sign in to your account to continue.</div>

@if (session('error'))
    <div class="auth-status" style="background:#fee2e2;color:#b91c1c;">{{ session('error') }}</div>
@endif

<form method="POST" action="{{ route('login') }}">
    @csrf

    <div class="mb-3">
        <label for="login">Email or Username</label>
        <div class="input-group">
            <span class="input-group-text"><i class="ri-user-3-line"></i></span>
            <input class="form-control" type="text" name="login" id="login" value="{{ old('login') }}" required autofocus autocomplete="username" placeholder="you@example.com">
        </div>
        @error('login')<span class="field-err">{{ $message }}</span>@enderror
    </div>

    <div class="mb-3">
        <label for="password">Password</label>
        <div class="input-group">
            <span class="input-group-text"><i class="ri-lock-2-line"></i></span>
            <input class="form-control" type="password" name="password" id="password" required autocomplete="current-password" placeholder="••••••••">
        </div>
        @error('password')<span class="field-err">{{ $message }}</span>@enderror
    </div>

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="form-check">
            <input type="checkbox" class="form-check-input" id="remember_me" name="remember">
            <label class="form-check-label" for="remember_me" style="font-weight:500;">Remember me</label>
        </div>
        <a href="{{ route('password.request') }}" style="font-size:.85rem;">Forgot password?</a>
    </div>

    <button type="submit" class="btn-accent"><i class="ri-login-box-line me-1"></i> Sign In</button>
</form>

<div class="foot">Need an account? Ask your administrator.</div>
@endsection
