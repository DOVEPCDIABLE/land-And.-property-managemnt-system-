<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title', 'Workspace') · {{ $site->get('brand_name') }}</title><link rel="stylesheet" href="{{ asset('css/app.css') }}"><link rel="stylesheet" href="{{ asset('css/toasts.css') }}">@include('partials.brand-theme')</head>
<body>
@include('partials.toasts')
@auth
@php($workspaceAccess = app(\App\Services\WorkspaceAccess::class))
@php($activeMembership = auth()->user()->currentMembership())
<aside class="sidebar"><a class="brand" href="{{ route('dashboard') }}">@include('marketplace.logo')</a>
<a class="workspace-label workspace-switch-link" href="{{ route('workspaces.index') }}"><span><small>{{ strtoupper($activeMembership->workspace->kind) }} WORKSPACE</small><strong>{{ $activeMembership->workspace->name }}</strong></span><span>⇄</span></a>
<button class="workspace-menu-toggle" type="button" aria-expanded="false" aria-controls="workspace-navigation">Workspace menu <span>☰</span></button><nav id="workspace-navigation" aria-label="Main navigation" class="workspace-nav">@foreach($workspaceAccess->navigation(auth()->user()) as $group=>$items)
<details class="nav-group" @if(in_array($group,['Overview','Account']) || collect($items)->contains(fn($item)=>url()->current()===$workspaceAccess->url($item['module']))) open @endif><summary>{{ strtoupper($group) }} <span>⌄</span></summary>
@foreach($items as $item)@php($target = $workspaceAccess->url($item['module']))<a class="{{ url()->current()===$target ? 'selected' : '' }}" href="{{ $target }}"><span class="nav-glyph">{{ $item['module']==='overview'?'◫':'·' }}</span><span>{{ $item['label'] }}</span>@if($item['module']!=='system_settings'&&!config('workspaces.modules.'.$item['module'].'.implemented'))<small class="planned-label">Planned</small>@endif</a>@endforeach
</details>@endforeach
@if($activeMembership->profession && $activeMembership->roles->contains('name','service_provider') && auth()->user()->hasPermission('provider.view'))<details class="nav-group"><summary>YOUR PROFESSION <span>⌄</span></summary>@foreach(array_slice(config('workspaces.professions.'.$activeMembership->profession,[]),1) as $specialty)<a href="{{ route('modules.show','provider') }}"><span>{{ $specialty }}</span><small class="planned-label">Planned</small></a>@endforeach</details>@endif
<a href="{{ route('home') }}" class="marketplace-return">Visit {{ $site->get('brand_name') }} ↗</a></nav>
<div class="profile"><div class="avatar">{{ mb_substr(auth()->user()->name,0,1) }}</div><div><strong>{{ auth()->user()->name }}</strong><small>{{ $activeMembership->roles->map(fn($role)=>config('workspaces.roles.'.$role->name.'.label'))->implode(' · ') }}</small></div></div><form method="post" action="{{ route('logout') }}">@csrf<button class="logout">Sign out ↗</button></form></aside>
@endauth
<main class="{{ auth()->check() ? 'main' : 'auth-main' }}">
@auth<header class="topbar"><span>WORKSPACE <span class="crumb">/ @yield('title','Overview')</span></span><div><span class="live-dot"></span> {{ now()->timezone(config('ecosystem.display_timezone'))->format('D, d M Y') }}</div></header>@endauth
<div class="content">
@yield('content')
</div></main><script src="{{ asset('js/toasts.js') }}" defer></script><script src="{{ asset('js/workspaces.js') }}" defer></script><script src="{{ asset('js/platform-settings.js') }}" defer></script></body></html>
