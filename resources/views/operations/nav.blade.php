<nav class="ops-tabs" aria-label="Business operations">
@foreach(config('operations') as $key=>$definition)
@if(auth()->user()->hasPermission($definition['module'].'.view'))<a @class(['active'=>($entity??'')===$key]) href="{{ route('operations.index',$key) }}">{{ $definition['title'] }}</a>@endif
@endforeach
@if(auth()->user()->hasPermission('payments.view'))<a href="{{ route('operations.finance') }}">Financial report</a><a href="{{ route('payments.index') }}">Payment records</a>@endif
</nav>
