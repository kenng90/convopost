{{-- Included from wpbox message.blade.php when message.is_call_brief --}}
<div class="wa-call-brief text-left" style="color: #fff;">
    <div class="d-flex align-items-center mb-2" style="gap: 8px;">
        <span style="font-size: 1.25rem;">📞</span>
        <strong>@{{ callBriefTitle(message) }}</strong>
        <span v-if="callBriefPayload(message).handoff_requested" class="badge badge-warning">{{ __('Handoff') }}</span>
    </div>

    <div v-if="callBriefPayload(message).intent" class="mb-2 small" style="opacity: 0.9;">
        <strong>{{ __('Intent') }}:</strong> @{{ callBriefPayload(message).intent }}
        <span v-if="callBriefPayload(message).urgency"> · @{{ callBriefPayload(message).urgency }}</span>
    </div>

    <div v-if="callBriefBullets(message).length" class="mb-2">
        <div class="small font-weight-bold mb-1">{{ __('Summary') }}</div>
        <ul class="mb-0 pl-3" style="font-size: 0.875rem;">
            <li v-for="(bullet, i) in callBriefBullets(message)" :key="'b'+i">@{{ bullet }}</li>
        </ul>
    </div>
    <div v-else-if="message.value" class="mb-2 small" style="white-space: pre-wrap; opacity: 0.95;">@{{ message.value }}</div>

    <div v-if="callBriefFields(message).length" class="mb-2">
        <div class="small font-weight-bold mb-1">{{ __('Captured') }}</div>
        <div v-for="(field, i) in callBriefFields(message)" :key="'f'+i" class="mb-2" style="font-size: 0.875rem;">
            <div>
                <span v-html="callBriefFieldIcon(field.status)"></span>
                <strong>@{{ field.label || field.key }}:</strong>
                @{{ field.value || '—' }}
            </div>
            <div v-if="field.source_quote" class="ml-3 small font-italic" style="opacity: 0.85;">"@{{ field.source_quote }}"</div>
        </div>
    </div>

    <div v-if="callBriefPayload(message).missing_required && callBriefPayload(message).missing_required.length" class="mb-2 small">
        <strong>{{ __('Missing') }}:</strong> @{{ callBriefPayload(message).missing_required.join(', ') }}
    </div>

    <div v-if="callBriefPayload(message).handoff_reason" class="mb-2 small">
        <strong>{{ __('Handoff reason') }}:</strong> @{{ callBriefPayload(message).handoff_reason }}
    </div>

    <details v-if="callBriefPayload(message).transcript_excerpt" class="small" style="opacity: 0.9;">
        <summary>{{ __('Transcript') }}</summary>
        <pre class="mb-0 mt-1" style="white-space: pre-wrap; font-size: 0.8rem;">@{{ callBriefPayload(message).transcript_excerpt }}</pre>
    </details>
    <p v-else-if="message.value" class="mb-0 small" style="opacity: 0.9; white-space: pre-wrap;">@{{ message.value }}</p>
</div>
