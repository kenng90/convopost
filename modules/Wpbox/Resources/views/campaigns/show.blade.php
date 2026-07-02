@extends('general.index', $setup)

@section('customheading')
    @include('wpbox::campaigns.show._header', ['presenter' => $presenter, 'item' => $item])

    @if ($presenter->showMap() && config('wpbox.google_maps_enabled', true))
        @include('wpbox::campaigns.map')
    @endif

    @include('wpbox::campaigns.show._progress', ['analytics' => $analytics])

    <div class="mt-4">
        @if ($item->is_bot || $item->is_api)
            @include('wpbox::campaigns.infoboxes', ['item' => $item, 'total_contacts' => $total_contacts])
        @else
            @include('wpbox::campaigns.show._metrics', [
                'presenter' => $presenter,
                'item' => $item,
                'analytics' => $analytics,
                'total_contacts' => $total_contacts,
            ])
            @include('wpbox::campaigns.show._content-preview', ['contentPreview' => $contentPreview])
        @endif
    </div>
@endsection

@section('thead')
    @foreach ($presenter->messageTableHeaders() as $column)
        <th>{{ $column['label'] }}</th>
    @endforeach
@endsection

@section('tbody')
    @foreach ($setup['items'] as $message)
        @php $row = $presenter->messageRow($message); @endphp
        <tr>
            @if ($presenter->channel() === \Modules\Wpbox\Models\Campaign::CHANNEL_EMAIL)
                <td>{{ $row['email'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td>{{ $row['subject'] }}</td>
                <td>{{ \Illuminate\Support\Str::limit($row['message'], 120) }}</td>
            @else
                <td>{{ $row['phone'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td>{{ \Illuminate\Support\Str::limit($row['message'], 120) }}</td>
            @endif
            <td>
                <span class="badge badge-{{ $row['status_class'] }}">{{ $row['status'] }}</span>
            </td>
        </tr>
    @endforeach
@endsection
