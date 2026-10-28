@extends('layouts.app')
@section('title','Website image library')
@section('content')
<div class="page-heading"><div><div class="eyebrow">Super administrator</div><h1>Website image library</h1><p>Replace homepage and editorial media. Property gallery photos are managed on their property records.</p></div><a href="{{ route('operations.index','properties') }}">← Properties</a></div>
<div class="ops-library">@foreach($media as $item)<section class="card">@if($item->kind==='image')<img src="{{ asset($item->path) }}" alt="{{ $item->name }}">@else<video controls preload="metadata" src="{{ asset($item->path) }}"></video>@endif<h2>{{ $item->name }}</h2><form method="post" enctype="multipart/form-data" action="{{ route('properties.replace',$item->id) }}">@csrf<label>Replacement {{ $item->kind }}<input type="file" name="file" accept="{{ $item->kind==='video'?'video/mp4':'image/jpeg,image/png,image/webp' }}" required></label><button class="btn mt">Replace media ↗</button></form></section>@endforeach</div>
@endsection
