@php
    $label = $label ?? \Modules\Wpsupportlanding\Support\ChatDukaBrand::name();
    $showWordmark = $showWordmark ?? true;
    $wordmarkClass = $wordmarkClass ?? 'font-display font-800 text-lg tracking-tight text-ink';
@endphp
<span class="flex items-center gap-2.5">
    <img src="{{ \Modules\Wpsupportlanding\Support\ChatDukaBrand::markUrl() }}" alt="{{ $label }}" class="h-10 w-10 object-contain flex-shrink-0 rounded-xl">
    @if($showWordmark)
        <span class="{{ $wordmarkClass }}">
            Chat<span style="color:#E8A317;">Duka</span>
        </span>
    @endif
</span>
