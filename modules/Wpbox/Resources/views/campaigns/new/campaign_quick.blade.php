<div class="campign_elements">

    {{-- Campaign name --}}
    @include('partials.input', [
        'id'          => 'name',
        'name'        => 'Campaign name',
        'placeholder' => 'Name for your campaign',
        'required'    => false,
    ])

    {{-- Template selector --}}
    @include('partials.select', [
        'id'       => 'template_id',
        'name'     => 'Template',
        'data'     => $templates,
        'required' => true,
    ])

    {{-- Schedule --}}
    <div class="form-group">
        <label for="send_time" class="form-control-label">{{ __('Schedule send time') }}</label>
        <input class="form-control"
               type="datetime-local"
               id="send_time"
               name="send_time"
               min="{{ \Carbon\Carbon::now()->format('Y-m-d\TH:i') }}"
               @isset($_GET['send_time']) value="{{ $_GET['send_time'] }}" @endisset>
        <small class="text-muted">
            <strong>{{ __('Per client, based on the contact timezone') }}</strong>
        </small>
    </div>

    @include('partials.toggle', [
        'dlon'    => 'Send now',
        'dloff'   => 'Schedule send',
        'id'      => 'send_now',
        'name'    => 'Ignore schedule time and send now',
        'checked' => isset($_GET['send_now']),
    ])

    <button onclick="submitJustCampign()" class="btn btn-success mt-4">
        {{ __('Apply') }}
    </button>

</div>
