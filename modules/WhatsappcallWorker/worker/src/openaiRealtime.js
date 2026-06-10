import { config } from './config.js';
import { logInfo, logWarn, logError } from './logger.js';
import { runStubAiSession } from './stubAiSession.js';
import { runRealtimeCallSession } from './realtimeCallSession.js';
import { createSessionDebug, addWarning } from './sessionDebug.js';
import { resolveOpenAiApiKey } from './openaiKey.js';

/**
 * Run AI voice session — OpenAI Realtime when configured, otherwise stub.
 */
export async function runAiSession(ctx) {
  const openaiApiKey = resolveOpenAiApiKey(ctx.payload);
  const debug = createSessionDebug('unknown');
  debug.worker_mode = config.workerMode;
  debug.openai_configured = Boolean(openaiApiKey);
  debug.openai_model = config.openaiRealtimeModel;
  debug.openai_key_source = openaiApiKey ? 'company' : 'none';

  const useRealtime = config.workerMode === 'realtime' && Boolean(openaiApiKey);

  if (useRealtime) {
    debug.session_type = 'realtime';
    logInfo('Starting OpenAI Realtime voice session', {
      model: config.openaiRealtimeModel,
      voice: config.openaiVoice,
      instructions_chars: buildInstructionPreview(ctx.payload),
    });
    return runRealtimeCallSession({ ...ctx, openaiApiKey, debug });
  }

  debug.session_type = 'stub';

  if (config.workerMode === 'realtime' && !openaiApiKey) {
    addWarning(
      debug,
      'WORKER_MODE=realtime but no OpenAI API key for this call — falling back to STUB (no spoken audio)',
    );
    logError(
      'OpenAI API key missing for this call. ' +
        'Company OpenAI API key missing — add it under WhatsApp Calling → AI voice settings.',
    );
  } else {
    logWarn('Running STUB session (no spoken AI)', { worker_mode: config.workerMode });
  }

  return runStubAiSession({ ...ctx, debug });
}

function buildInstructionPreview(payload) {
  const len = (payload.system_context?.length || 0) + (payload.vector_context?.length || 0);
  return { system_context_chars: payload.system_context?.length || 0, vector_context_chars: payload.vector_context?.length || 0, total: len };
}
