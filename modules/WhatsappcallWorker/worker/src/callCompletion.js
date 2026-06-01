import { logInfo, logError, logWarn } from './logger.js';
import { createSessionDebug, finalizeDebug, addError } from './sessionDebug.js';
import { buildCallBriefStructured } from './callBriefSummary.js';
import { config } from './config.js';
import { resolveOpenAiApiKey } from './openaiKey.js';

export function buildErrorCompletionPayload({ payload, startedAt, debug, errorMessage }) {
  const sessionDebug = debug ?? createSessionDebug('error');
  sessionDebug.worker_mode = config.workerMode;
  sessionDebug.openai_configured = Boolean(resolveOpenAiApiKey(payload));
  if (errorMessage) {
    addError(sessionDebug, errorMessage);
  }
  finalizeDebug(sessionDebug);
  sessionDebug.end_reason = 'error';

  const duration = Math.round((Date.now() - startedAt) / 1000);
  const transcript = errorMessage ? `Call ended with error: ${errorMessage}` : 'Call ended.';

  return {
    duration_seconds: duration,
    transcript,
    handoff_requested: false,
    worker_debug: sessionDebug,
    structured: {
      intent: 'error',
      summary_bullets: errorMessage ? [`Worker error: ${errorMessage}`] : ['Call ended without session data.'],
      fields: [],
      missing_required: payload?.required_field_keys || [],
      handled_by: 'ai',
    },
  };
}

export async function postCallCompletion(laravel, completionPayload) {
  if (!completionPayload) {
    logError('postCallCompletion called with empty payload');
    return false;
  }

  try {
    await laravel.terminate();
  } catch (e) {
    logWarn('Laravel terminate failed (call may already be ended)', { error: e.message });
  }

  try {
    logInfo('Posting call completion to Laravel', {
      duration_seconds: completionPayload.duration_seconds,
      session_type: completionPayload.worker_debug?.session_type ?? null,
      summary_bullets: completionPayload.structured?.summary_bullets?.length ?? 0,
    });
    await laravel.complete(completionPayload);
    logInfo('Call completion accepted by Laravel');
    return true;
  } catch (e) {
    logError('Laravel complete failed', {
      error: e.message,
      status: e.status,
      body: e.body,
    });
    return false;
  }
}

export function buildSuccessCompletionPayload({ payload, startedAt, debug, transcript, reason }) {
  finalizeDebug(debug);
  debug.end_reason = reason;
  const duration = Math.round((Date.now() - startedAt) / 1000);

  return {
    duration_seconds: duration,
    transcript: transcript || `Call ended (${reason}).`,
    handoff_requested: false,
    worker_debug: debug,
    structured: buildCallBriefStructured({
      transcript: transcript || '',
      debug,
      reason,
      payload,
    }),
  };
}
