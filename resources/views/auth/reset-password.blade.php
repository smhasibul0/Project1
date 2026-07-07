@extends('auth.layout')
@section('title', 'Set new password')
@section('auth')
<h2>Set a new password</h2>
<div class="sub">Choose a strong new password for your account.</div>

<form method="POST" action="{{ route('password.store') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $request->route('token') }}">

    <div class="mb-3">
        <label for="email">Email address</label>
        <div class="input-group">
            <span class="input-group-text"><i class="ri-mail-line"></i></span>
            <input class="form-control" type="email" name="email" id="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username" placeholder="you@example.com">
        </div>
        @error('email')<span class="field-err">{{ $message }}</span>@enderror
    </div>

    <div class="mb-3">
        <label for="password">New password</label>
        <div class="input-group">
            <span class="input-group-text"><i class="ri-lock-2-line"></i></span>
            <input class="form-control" type="password" name="password" id="password" required autocomplete="new-password" placeholder="••••••••">
        </div>
        @error('password')<span class="field-err">{{ $message }}</span>@enderror
    </div>

    <div class="mb-3">
        <label for="password_confirmation">Confirm new password</label>
        <div class="input-group">
            <span class="input-group-text"><i class="ri-lock-2-line"></i></span>
            <input class="form-control" type="password" name="password_confirmation" id="password_confirmation" required autocomplete="new-password" placeholder="••••••••">
        </div>
        @error('password_confirmation')<span class="field-err">{{ $message }}</span>@enderror
    </div>

    <button type="submit" class="btn-accent"><i class="ri-lock-unlock-line me-1"></i> Reset Password</button>
</form>

<div class="foot"><a href="{{ route('login') }}"><i class="ri-arrow-left-line me-1"></i>Back to sign in</a></div>
@endsection
