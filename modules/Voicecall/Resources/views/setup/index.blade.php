@extends('layouts.app', ['title' => __('Voice Call Setup')])
@section('content')
<div class="header pb-8 pt-2 pt-md-7">
    <div class="container-fluid">
        <div class="header-body">
            <h1 class="mb-3 mt--3">📞 {{ __('Voice Call (AI phone)') }}</h1>
            <p class="text-muted">{{ __('Import phone numbers for AI assistants. Uses the same telephony provider as SMS (Telnyx or Twilio) from App Settings. Call summaries appear in contact chat.') }}</p>
        </div>
    </div>
</div>
<div class="container-fluid mt--8">
    @include('partials.flash')

    @if(!$credentialsOk)
        <div class="alert alert-warning">
            @if($telephony->isTelnyx())
                {{ __('Telnyx credentials missing. Set TELNYX_API_KEY and TELNYX_CONNECTION_ID in App Settings → Telephony.') }}
            @else
                {{ __('Twilio credentials missing. Set TWILIO_ACCOUNT_SID and TWILIO_AUTH_TOKEN in App Settings → Telephony.') }}
            @endif
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header">{{ __('Provider') }}: {{ $telephony->isTelnyx() ? 'Telnyx' : 'Twilio' }}</div>
        <div class="card-body">
            @if($telephony->isTelnyx())
                <p class="mb-1">{{ __('In Telnyx Portal → Voice → Call Control Application (your Connection), set webhook URL:') }}</p>
                <p class="mb-0"><code>{{ $webhookBase }}</code> (HTTP POST)</p>
                <p class="text-muted small mt-2">{{ __('Assign your Telnyx number to that connection. Events: call.initiated, call.answered, call.speak.ended, call.gather.ended, call.hangup.') }}</p>
            @else
                <p class="mb-1">{{ __('In Twilio Console → Phone Number → Voice, set:') }}</p>
                <ul class="mb-0">
                    <li><strong>A call comes in:</strong> <code>{{ $webhookBase }}/incoming</code> (HTTP POST)</li>
                    <li><strong>Call status changes:</strong> <code>{{ $webhookBase }}/status</code> (HTTP POST)</li>
                </ul>
            @endif
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">{{ __('Add phone number') }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('voicecall.numbers.store') }}">
                @csrf
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>{{ __('E.164 number') }}</label>
                            <input type="text" name="phone_number" class="form-control" placeholder="+15551234567" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>{{ __('Label') }}</label>
                            <input type="text" name="friendly_name" class="form-control" placeholder="{{ __('Support line') }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>{{ __('Flowmaker flow (optional)') }}</label>
                            <select name="voice_flow_id" class="form-control">
                                <option value="">{{ __('— None —') }}</option>
                                @foreach($flows as $flow)
                                    <option value="{{ $flow->id }}">{{ $flow->name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">{{ __('Uses flow training docs as agent knowledge.') }}</small>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>{{ __('Catalogs (reference for agent)') }}</label>
                    <div class="row">
                        @forelse($catalogs as $catalog)
                            <div class="col-md-4">
                                <label class="d-block">
                                    <input type="checkbox" name="catalog_ids[]" value="{{ $catalog->id }}"> {{ $catalog->name }}
                                </label>
                            </div>
                        @empty
                            <div class="col-12 text-muted">{{ __('No catalogs yet.') }}</div>
                        @endforelse
                    </div>
                </div>
                <div class="form-group">
                    <label>{{ __('Greeting') }}</label>
                    <textarea name="ai_greeting" class="form-control" rows="2" placeholder="{{ __('Hello, thanks for calling...') }}"></textarea>
                </div>
                <div class="form-group">
                    <label>{{ __('Handoff phrases (one per line)') }}</label>
                    <textarea name="handoff_phrases" class="form-control" rows="2" placeholder="speak to a person&#10;human agent"></textarea>
                </div>
                <div class="form-group">
                    <label>{{ __('Fields to capture') }}</label>
                    <label class="d-block mr-3"><input type="checkbox" name="required_field_keys[]" value="name" checked> {{ __('Name') }}</label>
                    <label class="d-block mr-3"><input type="checkbox" name="required_field_keys[]" value="phone" checked> {{ __('Phone') }}</label>
                    <label class="d-block mr-3"><input type="checkbox" name="required_field_keys[]" value="email"> {{ __('Email') }}</label>
                </div>
                <button type="submit" class="btn btn-primary" @if(!$credentialsOk) disabled @endif>{{ __('Add number') }}</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">{{ __('Configured numbers') }}</div>
        <div class="card-body p-0">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Number') }}</th>
                        <th>{{ __('Provider') }}</th>
                        <th>{{ __('Flow') }}</th>
                        <th>{{ __('Catalogs') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($numbers as $n)
                        <tr>
                            <td>
                                <strong>{{ $n->phone_number }}</strong>
                                @if($n->friendly_name)<br><small class="text-muted">{{ $n->friendly_name }}</small>@endif
                            </td>
                            <td>{{ $n->provider ?? $telephony->provider }}</td>
                            <td>{{ $n->flow_name ?? '—' }}</td>
                            <td>{{ count($n->catalog_ids ?? []) }}</td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('voicecall.numbers.destroy', $n) }}" class="d-inline" onsubmit="return confirm('{{ __('Remove?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">{{ __('Remove') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted p-4">{{ __('No numbers yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
