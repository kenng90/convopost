@extends('general.index', $setup)
@section('cardbody')
<form action="{{ $setup['action'] }}" method="POST" enctype="multipart/form-data">
        @csrf
        @isset($setup['isupdate'])
            @method('PUT')
        @endisset
        @isset($setup['inrow'])
            <div class="row">
        @endisset
            @include('partials.fields',['fiedls'=>$fields])
        @isset($setup['inrow'])
            </div>
        @endisset
        @if (isset($setup['isupdate']))
            <button type="submit" class="btn btn-primary">{{ __('Update')}}</button>
        @else
            <button type="submit" class="btn btn-primary">{{ __('Insert')}}</button>
        @endif
    </form>

    @isset($contact)
        <hr class="my-4">
        <h4 class="mb-3">{{ __('Messaging channels') }}</h4>
        @if($contact->channelIdentities->isEmpty())
            <p class="text-muted">{{ __('No Instagram/Messenger/WhatsApp identities linked yet. Identities are created when the contact messages you.') }}</p>
        @else
            <ul class="list-group mb-4">
                @foreach ($contact->channelIdentities as $identity)
                    @php
                        $channelEnum = $identity->channel instanceof \App\Enums\MessagingChannelType
                            ? $identity->channel
                            : \App\Enums\MessagingChannelType::tryFromString((string) $identity->channel);
                    @endphp
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>
                            <span class="badge {{ $channelEnum?->badgeClass() ?? 'badge-secondary' }}">{{ $channelEnum?->label() ?? $identity->channel }}</span>
                            <code class="ml-2">{{ $identity->external_id }}</code>
                        </span>
                        <small class="text-muted">{{ $identity->display_name }}</small>
                    </li>
                @endforeach
            </ul>
        @endif

        @if(isset($mergeCandidates) && $mergeCandidates->isNotEmpty())
            <h4 class="mb-3">{{ __('Merge another contact into this one') }}</h4>
            <form method="POST" action="{{ route('contacts.merge', $contact) }}" onsubmit="return confirm('{{ __('Merge the selected contact into this one? The other contact will be deleted.') }}')">
                @csrf
                <div class="form-row align-items-end">
                    <div class="form-group col-md-8">
                        <label>{{ __('Contact to merge (will be deleted)') }}</label>
                        <select name="secondary_contact_id" class="form-control select2init" required>
                            <option value="">{{ __('Select contact') }}</option>
                            @foreach ($mergeCandidates as $candidate)
                                <option value="{{ $candidate->id }}">
                                    #{{ $candidate->id }} — {{ $candidate->name }} {{ $candidate->phone ? '('.$candidate->phone.')' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <button type="submit" class="btn btn-warning">{{ __('Merge contacts') }}</button>
                    </div>
                </div>
            </form>
        @endif
    @endisset
@endsection

@section('js')
<script type="text/javascript">
setTimeout(() => {
    $('.select2init').select2({
        });
}, 1000);
</script>
@endsection
