<style>:root{@foreach($site->colors() as $key=>$value){{ $key }}:{{ $value }};@endforeach --brand-button-text:{{ $site->buttonText() }};--bg:{{ $site->get('background_color') }};}</style>
