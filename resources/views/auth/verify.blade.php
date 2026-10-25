@extends('layouts.app')
@section('title','Verify your email')
@section('content')<div class="card"><div class="eyebrow">One more step</div><h1>Verify your email address.</h1><p class="muted">Open the verification link sent to your email to access your workspace.</p><form method="post" action="{{ route('verification.send') }}">@csrf<button type="submit">Send another verification email</button></form></div>@endsection
