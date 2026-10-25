<div class="toast-region" aria-label="Notifications" aria-live="polite" aria-relevant="additions" data-toast-region>
    @foreach (['status' => 'success', 'success' => 'success', 'error' => 'error', 'warning' => 'warning', 'info' => 'info'] as $key => $type)
        @if (session()->has($key))
            <div class="toast toast-{{ $type }}" data-toast data-type="{{ $type }}" @if($type === 'error') role="alert" @else role="status" @endif aria-atomic="true">
                <span class="toast-icon" aria-hidden="true">{{ $type === 'success' ? '✓' : ($type === 'info' ? 'i' : '!') }}</span>
                <div class="toast-content"><strong class="toast-title">{{ ['success'=>'Success', 'error'=>'Something went wrong', 'warning'=>'Please note', 'info'=>'Update'][$type] }}</strong><p class="toast-message">{{ session($key) }}</p></div>
                <button class="toast-close" type="button" aria-label="Dismiss notification">×</button>
            </div>
        @endif
    @endforeach
    @if ($errors->any())
        <div class="toast toast-error" data-toast data-type="error" role="alert" aria-atomic="true">
            <span class="toast-icon" aria-hidden="true">!</span>
            <div class="toast-content"><strong class="toast-title">Please check your details</strong><ul class="toast-errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            <button class="toast-close" type="button" aria-label="Dismiss notification">×</button>
        </div>
    @endif
</div>
