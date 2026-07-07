@extends('auth.layout')
@section('title', 'Reset password')
@section('auth')
<h2>Forgot password?</h2>
<div class="sub">No problem — enter your email and we'll send you a reset link.</div>

@if (session('status'))
    <div class="auth-status">{{ session('status') }}</div>
@endif

<form method="POST" action="{{ route('password.email') }}">
    @csrf

    <div class="mb-3">
        <label for="email">Email address</label>
        <div class="input-group">
            <span class="input-group-text"><i class="ri-mail-line"></i></span>
            <input class="form-control" type="email" name="email" id="email" value="{{ old('email') }}" required autofocus placeholder="you@example.com">
        </div>
        @error('email')<span class="field-err">{{ $message }}</span>@enderror
    </div>

    <button type="submit" class="btn-accent"><i class="ri-mail-send-line me-1"></i> Send Reset Link</button>
</form>

<div class="foot"><a href="{{ route('login') }}"><i class="ri-arrow-left-line me-1"></i>Back to sign in</a></div>
@endsection
