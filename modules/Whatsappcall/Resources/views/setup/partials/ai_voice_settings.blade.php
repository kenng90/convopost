<div class="card mt-4">
    <div class="card-header">{{ __('Inbound call handling') }}</div>
    <div class="card-body">
        @php($aiVoiceReady = $settings['ai_voice_ready'] ?? false)
        @php($aiHandlingSelected = in_array($settings['call_handling'] ?? 'live', ['ai', 'ai_after_hours'], true))

        @if($aiHandlingSelected && ! $aiVoiceReady)
            <div class="alert alert-danger" role="alert">
                <strong>{{ __('AI voice is not configured') }}</strong>
                <p class="mb-0 mt-1">{{ __('You selected AI call handling but no OpenAI API key is saved. Incoming calls will ring live agents instead until you add your key below. Voice AI usage is billed only to your OpenAI account — we do not use the platform .env key.') }}</p>
            </div>
        @endif

        <div class="form-group">
            <label>{{ __('OpenAI API key (voice Realtime)') }} <span class="text-danger">*</span></label>
            <input type="password" class="form-control" name="ai_openai_api_key" autocomplete="off"
                placeholder="{{ ($settings['ai_openai_api_key_set'] ?? false) ? __('Leave blank to keep current key') : 'sk-...' }}">
            <small class="text-muted d-block">
                {{ __('Required to enable AI voice agents. All Realtime voice usage is charged to your OpenAI account (platform.openai.com).') }}
                @if($settings['ai_openai_api_key_set'] ?? false)
                    <span class="text-success d-block mt-1">{{ __('Key saved — AI voice can be enabled.') }}</span>
                @else
                    <span class="text-danger d-block mt-1">{{ __('No key saved — you cannot use AI voice agents until you add one.') }}</span>
                @endif
            </small>
        </div>

        <div class="form-group">
            <label>{{ __('Who answers WhatsApp voice calls') }}</label>
            <select class="form-control" name="call_handling">
                <option value="live" {{ ($settings['call_handling'] ?? 'live') === 'live' ? 'selected' : '' }}>{{ __('Live agents (browser)') }}</option>
                <option value="ai" {{ ($settings['call_handling'] ?? '') === 'ai' ? 'selected' : '' }} {{ ! $aiVoiceReady ? 'disabled' : '' }}>{{ __('AI voice agent only') }}</option>
                <option value="ai_after_hours" {{ ($settings['call_handling'] ?? '') === 'ai_after_hours' ? 'selected' : '' }} {{ ! $aiVoiceReady ? 'disabled' : '' }}>{{ __('AI after hours, live agents during business hours') }}</option>
            </select>
            <small class="text-muted d-block">
                {{ __('AI modes require your OpenAI API key above. Without it, only live agent mode is available.') }}
                @if(! $aiVoiceReady)
                    <span class="text-danger d-block">{{ __('Save an OpenAI API key first to select AI voice handling.') }}</span>
                @endif
            </small>
        </div>

        <div class="row">
            <!-- <div class="col-md-6">
                 <div class="form-group">
                    <label>{{ __('Flowmaker flow (knowledge & instructions)') }}</label>
                    <select class="form-control" name="ai_flow_id">
                        <option value="">{{ __('— None —') }}</option>
                        @foreach($flows as $flow)
                            <option value="{{ $flow->id }}" {{ (int) ($settings['ai_flow_id'] ?? 0) === (int) $flow->id ? 'selected' : '' }}>{{ $flow->name }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">{{ __('Uses flow training documents, LLM system prompt, and vector search.') }}</small>
                </div> 
            </div> -->
            <div class="col-md-6">
                <div class="form-group">
                    <label class="d-block">
                        <input type="checkbox" name="ai_enable_vector_search" value="1" {{ ($settings['ai_enable_vector_search'] ?? true) ? 'checked' : '' }}>
                        {{ __('Enable vector search on flow documents') }}
                    </label>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label class="d-block">
                <input type="checkbox" name="ai_send_invoice_after_call" value="1" {{ ($settings['ai_send_invoice_after_call'] ?? true) ? 'checked' : '' }}>
                {{ __('Send WhatsApp invoice after voice call when customer orders a catalog product') }}
            </label>
            <small class="text-muted d-block">{{ __('Runs when the call ends — matches products from catalogs above against what the caller said. Not used for text chat flows.') }}</small>
        </div>

        <div class="card bg-light mt-3 mb-3">
            <div class="card-body py-3">
                <h6 class="mb-2">{{ __('Voice AI bookings (Reminders)') }}</h6>
                <label class="d-block mb-2">
                    <input type="checkbox" name="ai_booking_enabled" value="1" {{ ($settings['ai_booking_enabled'] ?? false) ? 'checked' : '' }}>
                    {{ __('Let voice AI check availability and book appointments / events on behalf of callers') }}
                </label>
                <div class="row">
                    <div class="col-md-4">
                        <label class="d-block">
                            <input type="checkbox" name="ai_booking_appointments" value="1" {{ ($settings['ai_booking_appointments'] ?? true) ? 'checked' : '' }}>
                            {{ __('Appointments') }}
                        </label>
                    </div>
                    <div class="col-md-4">
                        <label class="d-block">
                            <input type="checkbox" name="ai_booking_events" value="1" {{ ($settings['ai_booking_events'] ?? true) ? 'checked' : '' }}>
                            {{ __('Events') }}
                        </label>
                    </div>
                    <div class="col-md-4">
                        <label class="d-block">
                            <input type="checkbox" name="ai_booking_send_payment_link" value="1" {{ ($settings['ai_booking_send_payment_link'] ?? true) ? 'checked' : '' }}>
                            {{ __('Send payment link on WhatsApp for paid bookings') }}
                        </label>
                    </div>
                </div>
                <div class="form-group mt-2 mb-0">
                    <label>{{ __('Payment hold (minutes)') }}</label>
                    <input type="number" class="form-control form-control-sm" style="max-width: 8rem" name="ai_booking_hold_minutes"
                        min="5" max="60" value="{{ (int) ($settings['ai_booking_hold_minutes'] ?? 15) }}">
                    <small class="text-muted">{{ __('Unpaid voice bookings are released after this time.') }}</small>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>{{ __('Catalogs (product reference for AI)') }}</label>
            <div class="row">
                @forelse($catalogs as $catalog)
                    <div class="col-md-4">
                        <label class="d-block">
                            <input type="checkbox" name="ai_catalog_ids[]" value="{{ $catalog->id }}"
                                {{ in_array($catalog->id, $settings['ai_catalog_ids'] ?? []) ? 'checked' : '' }}>
                            {{ $catalog->name }}
                        </label>
                    </div>
                @empty
                    <div class="col-12 text-muted">{{ __('No catalogs yet.') }}</div>
                @endforelse
            </div>
        </div>

        <!-- <div class="form-group">
            <label class="d-block">
                <input type="checkbox" name="use_builtin_worker" value="1" {{ ($settings['use_builtin_worker'] ?? false) ? 'checked' : '' }}>
                {{ __('Use built-in worker') }} (<code>php artisan whatsappcall:worker</code>)
            </label>
            <small class="text-muted d-block">{{ __('Uses http://127.0.0.1:8787 — run the worker in a separate terminal.') }}</small>
        </div> -->

        <!-- <div class="row">
            <div class="col-md-8">
                <div class="form-group">
                    <label>{{ __('AI worker URL') }}</label>
                    <input type="url" class="form-control" name="ai_worker_url" value="{{ $settings['ai_worker_url'] ?? '' }}" placeholder="http://127.0.0.1:8787">
                    <small class="text-muted">{{ __('Built-in: :url', ['url' => config('whatsappcallworker.default_url', 'http://127.0.0.1:8787')]) }}</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>{{ __('Worker secret') }}</label>
                    <input type="password" class="form-control" name="ai_worker_secret" placeholder="{{ __('Leave blank to keep') }}">
                </div>
            </div>
        </div> -->

        <div class="form-group">
            <label>{{ __('Spoken language') }}</label>
            <select class="form-control" name="ai_spoken_language">
                @foreach(($settings['ai_spoken_language_options'] ?? []) as $code => $label)
                    <option value="{{ $code }}" {{ ($settings['ai_spoken_language'] ?? 'en') === $code ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <small class="text-muted d-block">{{ __('The agent stays in this language for the whole call. Whisper transcription is pinned to the same language so background noise is less likely to trigger a switch.') }}</small>
        </div>

        <div class="form-group">
            <label>{{ __('AI greeting (optional)') }}</label>
            <textarea class="form-control" name="ai_greeting" rows="2">{{ $settings['ai_greeting'] ?? '' }}</textarea>
            <small class="text-muted d-block">{{ __('Spoken AI uses OpenAI Realtime on your API key. Also used to seed vector search at call start.') }}</small>
        </div>

        <div class="form-group">
            <label class="d-block">
                <input type="checkbox" name="ai_mention_capabilities_in_greeting" value="1" {{ ($settings['ai_mention_capabilities_in_greeting'] ?? true) ? 'checked' : '' }}>
                {{ __('In the opening greeting, briefly say what the agent can help with (products, bookings, questions)') }}
            </label>
            <small class="text-muted d-block">{{ __('Auto-built from selected catalogs, bookable services/events, and the knowledge flow. Mentions categories and a few examples — not the full catalog.') }}</small>
        </div>

        <div class="form-group mb-0">
            <label>{{ __('Required fields to collect on AI calls') }}</label>
            <div class="row">
                @php($selected = $settings['ai_required_fields'] ?? [])
                <div class="col-md-4">
                    <label class="d-block"><input type="checkbox" name="ai_required_fields[]" value="name" {{ in_array('name', $selected) ? 'checked' : '' }}> {{ __('Name') }}</label>
                    <label class="d-block"><input type="checkbox" name="ai_required_fields[]" value="email" {{ in_array('email', $selected) ? 'checked' : '' }}> {{ __('Email') }}</label>
                    <label class="d-block"><input type="checkbox" name="ai_required_fields[]" value="phone" {{ in_array('phone', $selected) ? 'checked' : '' }}> {{ __('Phone') }}</label>
                </div>
                <div class="col-md-8">
                    @foreach($customFields as $field)
                        <label class="d-block">
                            <input type="checkbox" name="ai_required_fields[]" value="{{ $field->name }}" {{ in_array($field->name, $selected) || in_array((string) $field->id, $selected) ? 'checked' : '' }}>
                            {{ $field->name }}
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
