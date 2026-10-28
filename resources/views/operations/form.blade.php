@extends('layouts.app')
@section('title',($record?'Edit ':'Create ').$d['title'])
@section('content')
<div class="page-heading"><div><div class="eyebrow">{{ $d['title'] }}</div><h1>{{ $record?'Update record':'Create record' }}</h1></div><a href="{{ route('operations.index',$entity) }}">← Back to records</a></div>
@include('operations.nav')
<form class="card ops-form" method="post" enctype="multipart/form-data" action="{{ $record?route('operations.update',[$entity,$record->id]):route('operations.store',$entity) }}">@csrf @if($record)@method('patch')@endif
<div class="form-grid">
@foreach($d['fields'] as $key=>$field)
@php
$type=$field['type']; $value=old($key,$record?->$key??$defaults[$key]??($key==='service_listing_id'?request('service_listing_id'):null));
if(!session()->hasOldInput() && $record && $type==='money') { $value=\App\Services\Payments\Money::major($record->{$field['column']},$record->currency,$record->minor_unit); }
if(!session()->hasOldInput() && $record && $type==='tags') { $value=implode(', ',$record->$key??[]); }
if(!session()->hasOldInput() && $record && $type==='date') { $value=$record->$key?->format('Y-m-d'); }
if(!session()->hasOldInput() && $record && $type==='datetime-local') { $value=$record->$key?->timezone(config('ecosystem.display_timezone'))->format('Y-m-d\TH:i'); }
@endphp
<div @class(['ops-wide'=>in_array($type,['textarea','items','media'])])>
@if($type==='checkbox'||$type==='consent')<label class="ops-check"><input type="checkbox" name="{{ $key }}" value="1" @checked($value)>{{ $field['label'] }}</label>
@elseif($type==='items')
<label>{{ $field['label'] }}</label><div data-invoice-items>
@php($items=old('items',$record?->items??$defaults['items']??[['description'=>'','quantity'=>1,'unit_price'=>'']]))
@foreach($items as $index=>$item)<div class="ops-item"><input name="items[{{ $index }}][description]" placeholder="Description" aria-label="Item description" required value="{{ $item['description'] }}"><input name="items[{{ $index }}][quantity]" type="number" min="1" max="100000" aria-label="Quantity" value="{{ $item['quantity'] }}" required><input name="items[{{ $index }}][unit_price]" inputmode="decimal" aria-label="Unit price" placeholder="Unit price" value="{{ $item['unit_price']??\App\Services\Payments\Money::major($item['unit_minor']??0,$record?->currency??$money->default(),$record?->minor_unit) }}" required></div>@endforeach</div><button class="btn secondary" type="button" data-add-invoice-item>Add item +</button>
@else
<label for="op-{{ $key }}">{{ $field['label'] }}@if($field['required']??false) * @endif</label>
@if(in_array($type,['select','reference','organisation','public_service','member','currency']))<select id="op-{{ $key }}" name="{{ $key }}" @required($field['required']??false)><option value="">Select…</option>@php($options=$type==='currency'?collect($money->enabled())->mapWithKeys(fn($c,$code)=>[$code=>$code.' — '.$c['name']])->all():($field['options']??$choices[$key]??[]))@foreach($options as $option=>$label)<option value="{{ $option }}" @selected((string)($value??($type==='currency'?$money->default():''))===(string)$option)>{{ $label }}</option>@endforeach</select>
@elseif($type==='textarea')<textarea id="op-{{ $key }}" name="{{ $key }}" rows="4" @required($field['required']??false)>{{ $value }}</textarea>
@elseif($type==='file'||$type==='media')<input id="op-{{ $key }}" type="file" name="{{ $type==='media'?$key.'[]':$key }}" @if($type==='media')multiple accept="image/jpeg,image/png,image/webp,video/mp4"@else accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp"@endif @required(($field['required']??false)&&!$record)>@if($record&&$type==='file')<small>Leave blank to retain the current file. Files are downloaded only by authorised workspace members.</small>@endif
@else<input id="op-{{ $key }}" name="{{ $key }}" value="{{ $value??(in_array($type,['money','percent','integer','progress','decimal'])?'0':'') }}" type="{{ in_array($type,['date','datetime-local','email','month'])?$type:(in_array($type,['integer','progress','decimal','percent'])?'number':'text') }}" @if(in_array($type,['money','decimal','percent']))inputmode="decimal" step="any"@endif @required($field['required']??false)>@if($type==='tags')<small>Separate amenities with commas.</small>@endif
@endif
@endif
@error($key)<p class="error-text">{{ $message }}</p>@enderror
</div>
@endforeach
@if(isset($d['states']))<div><label for="op-status">Status</label><select id="op-status" name="status">@foreach($d['states'] as $state)<option value="{{ $state }}" @selected(old('status',$record?->status??$d['initial'])===$state)>{{ $state }}</option>@endforeach</select></div>@endif
<div><label for="op-assigned">Assigned staff member</label><select id="op-assigned" name="assigned_to"><option value="">{{ $entity==='appointments'?'Myself':'Unassigned' }}</option>@foreach(auth()->user()->currentWorkspace()->memberships()->with('user')->where('is_active',true)->get()->filter(function($m) use($d) { $a=auth()->user(); $scope=app(\App\Services\WorkspaceAccess::class)->scope($a,$d['module'].'.assign'); return $m->user_id===$a->id || ($scope==='organisation'||$scope==='platform'||($scope==='branch'&&$m->branch_id===$a->currentMembership()->branch_id)||($scope==='team'&&$m->team_id===$a->currentMembership()->team_id)); }) as $member)<option value="{{ $member->user_id }}" @selected((string)old('assigned_to',$record?->assigned_to)===(string)$member->user_id)>{{ $member->user->name }}</option>@endforeach</select></div>
</div>
@if($entity==='payroll')<p class="help">Records do not transfer money. Statutory deductions must be entered using your approved payroll calculations. Approve a draft before recording it paid.</p>@endif
@if($entity==='leases')<p class="help">Activating a lease checks for overlapping dates. Occupancy is calculated from active leases and their dates.</p>@endif
@if($entity==='invoices')<p class="help">Draft invoices can be edited. Issued amounts are preserved; record receipts against their outstanding balance.</p>@endif
<button class="btn mt" type="submit">Save record ↗</button>
</form>
@endsection
