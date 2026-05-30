<div class="card mt-4">
    <div class="card-header">{{ __('Inbound call handling') }}</div>
    <div class="card-body">
        <div class="form-group">
            <label>{{ __('Who answers WhatsApp voice calls') }}</label>
            <select class="form-control" name="call_handling">
                <option value="live" {{ ($settings['call_handling'] ?? 'live') === 'live' ? 'selected' : '' }}>{{ __('Live agents (browser)') }}</option>
                <option value="ai" {{ ($settings['call_handling'] ?? '') === 'ai' ? 'selected' : '' }}>{{ __('AI voice agent only') }}</option>
                <option value="ai_after_hours" {{ ($settings['call_handling'] ?? '') === 'ai_after_hours' ? 'selected' : '' }}>{{ __('AI after hours, live agents during business hours') }}</option>
            </select>
            <small class="text-muted d-block">{{ __('AI mode sends calls to the media worker only — agents will not see a ringing modal.') }}</small>
        </div>

        <div class="row">
            <div class="col-md-6">
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
            </div>
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

        <div class="form-group">
            <label class="d-block">
                <input type="checkbox" name="use_builtin_worker" value="1" {{ ($settings['use_builtin_worker'] ?? false) ? 'checked' : '' }}>
                {{ __('Use built-in worker') }} (<code>php artisan whatsappcall:worker</code>)
            </label>
            <small class="text-muted d-block">{{ __('Uses http://127.0.0.1:8787 — run the worker in a separate terminal.') }}</small>
        </div>

        <div class="row">
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
        </div>

        <div class="form-group">
            <label>{{ __('AI greeting (optional)') }}</label>
            <textarea class="form-control" name="ai_greeting" rows="2">{{ $settings['ai_greeting'] ?? '' }}</textarea>
            <small class="text-muted d-block">{{ __('Spoken AI uses OpenAI Realtime (WORKER_MODE=realtime + OPENAI_API_KEY). Also used to seed vector search at call start.') }}</small>
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
