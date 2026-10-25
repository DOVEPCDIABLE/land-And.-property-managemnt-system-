<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">@switch($name)
@case('heart')<path d="M20.8 4.6a5.4 5.4 0 0 0-7.6 0L12 5.8l-1.2-1.2a5.4 5.4 0 0 0-7.6 7.6L12 21l8.8-8.8a5.4 5.4 0 0 0 0-7.6Z"/>@break
@case('bed')<path d="M3 18v-7h18v7M3 15h18M5 11V6h14v5M7 9h3m4 0h3M3 18v2m18-2v2"/>@break
@case('bath')<path d="M3 12h18v3a5 5 0 0 1-5 5H8a5 5 0 0 1-5-5v-3ZM6 12V5a2 2 0 0 1 4 0M7 20v2m10-2v2"/>@break
@case('area')<path d="M4 9V4h5m6 0h5v5m0 6v5h-5M9 20H4v-5M4 4l5 5m11-5-5 5M4 20l5-5m11 5-5-5"/>@break
@case('pin')<path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2"/>@break
@case('arrow')<path d="M5 19 19 5M5 5h14v14"/>@break
@case('search')<circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/>@break
@case('message')<path d="M21 11a9 9 0 0 1-9 9H4l-3 2 2-6a9 9 0 1 1 18-5Z"/><path d="M7 11h10m-10 4h6"/>@break
@default<path d="m3 10 9-7 9 7v10H3V10ZM9 20v-7h6v7"/>
@endswitch</svg>
