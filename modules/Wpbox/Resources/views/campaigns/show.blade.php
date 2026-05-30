@extends('general.index', $setup)

@section('customheading')
    @if (config('wpbox.google_maps_enabled',true))
      @include('wpbox::campaigns.map',$item)
    @endif
   <div class="mt-4">
    @include('wpbox::campaigns.infoboxes', ['item' => $item])
   </div>
   
@endsection

@section('thead')
    <th>{{ __('Phone') }}</th>
    <th>{{ __('Name') }}</th>
    <th>{{ __('Message') }}</th>
    <th>{{ __('Status') }}</th>
@endsection
@section('tbody')
    @foreach ($setup['items'] as $message)
        <tr>
          <td>{{ $message->contact->phone ?? __('Unknown') }}</td>
          <td>{{ $message->contact->name ?? __('Unknown') }}</td>
          <td>{{ $message->value }}</td>
          <td>
              @if ( $message->status==0)
              <span class="badge badge-dot mr-4">
                  <i class="bg-warning"></i>
                  <span class="status">{{ __('PENDING SENT')}} {{ __( $message->error)}}</span>
                </span> 
              @elseif ( $message->status==1)
              <span class="badge badge-dot mr-4">
                  <i class="bg-warning"></i>
                  <span class="status">{{ __('SENT')}} {{ __( $message->error)}}</span>
                </span>
              @elseif( $message->status==2)
                  {{ __('SENT')}} 
              @elseif( $message->status==3)
              <span class="badge badge-dot mr-4">
                  <i class="bg-info"></i>
                  <span class="status">{{ __('DELIVERED')}} {{ __( $message->error)}}</span>
                </span>
              @elseif( $message->status==4)
              <span class="badge badge-dot mr-4">
                  <i class="bg-success"></i>
                  <span class="status">{{ __('READ')}} {{ __( $message->error)}}</span>
                </span>
              @elseif( $message->status==5)
              <span class="badge badge-dot mr-4">
                  <i class="bg-danger"></i>
                  <span class="status">{{ __('FAILED')}} : {{ __( $message->error)}} </span>
                </span>
              @endif
          </td>
        </tr>
    @endforeach
@endsection