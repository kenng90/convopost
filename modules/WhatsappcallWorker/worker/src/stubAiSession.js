import { config } from './config.js';
import { logInfo, logWarn } from './logger.js';
import { finalizeDebug } from './sessionDebug.js';
import { buildSuccessCompletionPayload } from './callCompletion.js';

/**
 * Stub AI: keeps call alive for a short period, then returns completion payload.
 */
export async function runStubAiSession({ payload, startedAt, debug }) {
  const durationMs = config.stubCallDurationMs;
  const simulateHandoff = false;

  logInfo('STUB session active — caller will hear silence', {
    duration_ms: durationMs,
    worker_mode: config.workerMode,
    openai_key_set: false,
    hint: 'Add company OpenAI API key in WhatsApp Calling setup',
  });

  await sleep(Math.min(durationMs, 3000));

  const required = payload.required_field_keys || [];
  const fields = buildStubFields(required, payload);
  const missing = required.filter(
    (key) => !fields.some((f) => f.key === key && ['confirmed', 'corrected'].includes(f.status)),
  );

  await sleep(Math.max(0, durationMs - 3000));

  finalizeDebug(debug);
  debug.end_reason = 'stub_completed';

  const transcript = buildStubTranscript(payload, fields, simulateHandoff);
  const base = buildSuccessCompletionPayload({
    payload,
    startedAt,
    debug,
    transcript,
    reason: 'stub_completed',
  });

  base.structured = {
    intent: 'general_inquiry',
    urgency: 'medium',
    summary_bullets: [
      'STUB mode — no spoken AI audio (add company OpenAI API key in WhatsApp Calling setup).',
      payload.ai_greeting
        ? `Configured greeting (text only): ${payload.ai_greeting.slice(0, 80)}`
        : 'AI voice agent handled the call in stub mode.',
    ],
    fields,
    missing_required: missing,
    handoff_requested: simulateHandoff,
    handled_by: 'ai',
    transcript_excerpt: transcript.slice(0, 2000),
    duration_seconds: base.duration_seconds,
  };
  base.handoff_requested = simulateHandoff;

  logInfo('STUB session completed', { duration_s: base.duration_seconds });
  return base;
}

function buildStubFields(required, payload) {
  const contactName = (payload.contact_name || '').trim();
  const defaults = {
    name: {
      label: 'Name',
      value: contactName,
      quote: contactName ? null : 'My name is on my account',
    },
    email: { label: 'Email', value: '', quote: null },
    phone: {
      label: 'Phone',
      value: payload.wa_user_id ? `+${String(payload.wa_user_id).replace(/^\+/, '')}` : '',
      quote: 'You can reach me on this number',
    },
  };

  const keys = required.length ? required : ['name'];
  return keys.map((key) => {
    const d = defaults[key] || { label: key, value: 'Provided on call', quote: null };
    const hasValue = d.value !== null && d.value !== '';
    return {
      key,
      label: d.label || key,
      value: hasValue ? d.value : null,
      status: hasValue ? 'confirmed' : 'missing',
      source_quote: d.quote,
    };
  });
}

function buildStubTranscript(payload, fields, handoff) {
  const lines = [];
  lines.push('[STUB MODE — no audio was spoken to the caller]');
  if (payload.system_context) {
    lines.push('[Context] ' + payload.system_context.slice(0, 400));
  }
  if (payload.vector_context) {
    lines.push('[Vector] ' + payload.vector_context.slice(0, 400));
  }
  lines.push('[AI] ' + (payload.ai_greeting || 'Hello, thanks for calling. How can I help you today?'));
  lines.push('[Caller] I need some help with my order.');
  for (const f of fields) {
    if (f.source_quote) lines.push(`[Caller] ${f.source_quote}`);
  }
  if (handoff) lines.push('[Caller] I would like to speak to a real person please.');
  lines.push('[AI] Thank you for calling. Goodbye.');
  return lines.join('\n');
}

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}
