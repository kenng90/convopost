@php
    $label = $label ?? 'UnganishaHub';
    $showWordmark = $showWordmark ?? true;
    $wordmarkClass = $wordmarkClass ?? 'font-display font-800 text-lg tracking-tight text-ink';
@endphp
<span class="flex items-center gap-2.5">
    <img src="{{ \Modules\Wpsupportlanding\Support\UnganishaBrand::markUrl() }}" alt="{{ $label }}" class="h-10 w-10 object-contain flex-shrink-0 rounded-xl">
    @if($showWordmark)
        <span class="{{ $wordmarkClass }}">Unganisha<span style="color:#0E8A7A;">Hub</span></span>
    @endif
</span>
