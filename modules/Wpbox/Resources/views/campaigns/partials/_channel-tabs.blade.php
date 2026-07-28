<ul class="nav nav-pills mb-4">
    @foreach ($channelLabels as $channelKey => $channelLabel)
        @php
            $tabQuery = array_merge(request()->except('page'), ['channel' => $channelKey]);
            $isActive = ($activeChannel ?? \Modules\Wpbox\Models\Campaign::CHANNEL_WHATSAPP) === $channelKey;
        @endphp
        <li class="nav-item">
            <a
                href="{{ route('campaigns.index', $tabQuery) }}"
                class="nav-link {{ $isActive ? 'active' : '' }}"
            >
                {{ __($channelLabel) }}
                @if (isset($channelCounts[$channelKey]))
                    <span class="badge badge-pill {{ $isActive ? 'badge-light' : 'badge-secondary' }} ml-1">
                        {{ $channelCounts[$channelKey] }}
                    </span>
                @endif
            </a>
        </li>
    @endforeach
</ul>
