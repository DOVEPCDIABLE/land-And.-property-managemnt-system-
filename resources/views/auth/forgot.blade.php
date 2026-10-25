@extends('layouts.app')
@section('title','Reset password')
@section('content')<div class="card auth-card"><div class="eyebrow">Account recovery</div><h1>Forgot your password?</h1><p class="muted">Enter your email to request a reset link.</p><form method="post" action="{{ route('password.email') }}">@csrf<label for="email">Email address</label><input id="email" name="email" type="email" required value="{{ old('email') }}"><button class="btn full" type="submit">Send reset link</button></form><p class="mt"><a href="{{ route('login') }}">Back to sign in</a></p></div>@endsection
