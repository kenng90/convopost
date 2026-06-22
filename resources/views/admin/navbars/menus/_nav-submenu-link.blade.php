@php
    $subParams = $submenu['params'] ?? [];
    $isActive = Route::currentRouteName() == $submenu['route']
        || ($submenu['route'] === 'whatsapp-flows.index' && request()->is('whatsapp-flows*') && ! request()->is('whatsapp-flows/responses*'));
@endphp
<li class="nav-item">
    <a class="nav-link @if ($isActive) active @endif"
        href="{{ route($submenu['route'], $subParams) }}">
        <i class="{{ $submenu['icon'] ?? 'ni ni-app' }}"></i> {{ __($submenu['name']) }}
    </a>
</li>
