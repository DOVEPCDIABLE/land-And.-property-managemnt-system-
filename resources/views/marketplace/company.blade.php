@extends('marketplace.layout')
@section('title', match($page) {'about'=>'About '.$site->get('brand_name'), 'founder'=>'A message from the founder', 'workspaces'=>'WorkSpaces hospitality portfolio'})
@section('content')
<section class="wrap company-hero">
    <nav class="company-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span>/</span><a href="{{ route('company.show','about') }}">Our story</a></nav>
    <p class="kicker">{{ $page==='founder'?'A MESSAGE FROM THE FOUNDER':($page==='workspaces'?'THE WORKSPACES PORTFOLIO':$company['positioning']) }}</p>
    <h1>@if($page==='about')Built with purpose.<br><em>Created to endure.</em>@elseif($page==='founder')A decision to build.<br><em>A vision to last.</em>@else WorkSpaces.<br><em>Hospitality, considered.</em>@endif</h1>
    <p class="company-lead">{{ match($page) {'about'=>$company['introduction'], 'founder'=>'A message from '.$company['founder']['name'].', '.$company['founder']['role'].'.', 'workspaces'=>$company['workspaces']['introduction']} }}</p>
</section>

@if($page==='about')
<section class="wrap company-story">
    <div class="company-story-image"><img src="{{ app(\App\Services\SiteMedia::class)->url('house.jpg') }}" alt="Contemporary architecture illustrating a considered approach to development" loading="lazy"><span>Architectural inspiration · illustrative photography</span></div>
    <div class="company-story-copy"><p class="kicker">THE PHILOSOPHY</p><h2>Value through<br><em>the whole lifecycle.</em></h2><p>{{ $company['philosophy'] }}</p><p>Every project is evaluated against four fundamental objectives.</p><ol class="company-objectives">@foreach($company['objectives'] as $objective)<li><span>0{{ $loop->iteration }}</span>{{ $objective }}</li>@endforeach</ol></div>
</section>
<section class="company-purpose"><div class="wrap company-purpose-grid"><div><p class="kicker orange-text">OUR VISION</p><h2>A long-term<br><em>point of view.</em></h2><p>{{ $company['vision'] }}</p></div><div><p class="kicker orange-text">OUR MISSION</p><h2>Discipline in<br><em>every detail.</em></h2><p>{{ $company['mission'] }}</p></div></div></section>
<section class="section wrap"><div class="section-heading"><div><p class="kicker">CORPORATE VALUES</p><h2>The principles<br><em>behind the work.</em></h2></div></div><div class="company-values">@foreach($company['values'] as $name=>$description)<article><span>0{{ $loop->iteration }}</span><h3>{{ $name }}</h3><p>{{ $description }}</p></article>@endforeach</div></section>
@elseif($page==='founder')
<article class="wrap founder-letter"><div class="founder-aside"><span class="founder-initials" aria-hidden="true">OT</span><h2>{{ $company['founder']['name'] }}</h2><p>{{ $company['founder']['role'] }}</p><a class="underlink" href="{{ route('company.show','about') }}">Our vision & values ↗</a></div><div class="founder-prose">@foreach($company['founder']['message'] as $paragraph)<p>{{ $paragraph }}</p>@endforeach<div class="founder-signature"><strong>{{ $company['founder']['name'] }}</strong><span>{{ $company['founder']['role'] }}</span></div><button type="button" class="button black" data-inquiry="support" data-context="Partnership conversation">Start a conversation <span>↗</span></button></div></article>
@else
<section class="wrap company-story"><div class="company-story-image"><img src="{{ app(\App\Services\SiteMedia::class)->url('interior.jpg') }}" alt="Interior design inspiration for serviced hospitality spaces, not a photograph of a WorkSpaces development" loading="lazy"><span>Hospitality inspiration · not a WorkSpaces project photograph</span></div><div class="company-story-copy"><p class="kicker">HOSPITALITY & SERVICED APARTMENTS</p><h2>{{ $company['workspaces']['title'] }}</h2><p>{{ $company['workspaces']['description'] }}</p><p>The development is introduced in {{ $company['name'] }}’s founder message as the first step towards an organisation recognised for the quality of its assets and the experiences they provide.</p><p class="fine-print">This page introduces the portfolio vision. Project locations, specifications, delivery dates, prices and booking availability have not been provided.</p><button type="button" class="button black" data-inquiry="support" data-context="WorkSpaces hospitality portfolio">Enquire about WorkSpaces <span>↗</span></button></div></section>
<section class="company-purpose"><div class="wrap"><p class="kicker orange-text">THE DEVELOPMENT PRINCIPLES</p><div class="company-values">@foreach($company['objectives'] as $objective)<article><span>0{{ $loop->iteration }}</span><h3>{{ $objective }}</h3></article>@endforeach</div></div></section>
@endif

<section class="wrap company-related" aria-label="Explore the company"><a href="{{ route('company.show','about') }}" @if($page==='about')aria-current="page"@endif>Our story & values <span>↗</span></a><a href="{{ route('company.show','founder') }}" @if($page==='founder')aria-current="page"@endif>The founder’s message <span>↗</span></a><a href="{{ route('company.show','workspaces') }}" @if($page==='workspaces')aria-current="page"@endif>WorkSpaces portfolio <span>↗</span></a></section>
@endsection
