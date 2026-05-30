import { config } from './config.js';
import { logInfo, logWarn, logError } from './logger.js';
import { runStubAiSession } from './stubAiSession.js';
import { runRealtimeCallSession } from './realtimeCallSession.js';
import { createSessionDebug, addWarning } from './sessionDebug.js';

/**
 * Run AI voice session — OpenAI Realtime when configured, otherwise stub.
 */
export async function runAiSession(ctx) {
  const debug = createSessionDebug('unknown');
  debug.worker_mode = config.workerMode;
  debug.openai_configured = Boolean(config.openaiApiKey);
  debug.openai_model = config.openaiRealtimeModel;

  const useRealtime =
    config.workerMode === 'realtime' && Boolean(config.openaiApiKey);

  if (useRealtime) {
    debug.session_type = 'realtime';
    logInfo('Starting OpenAI Realtime voice session', {
      model: config.openaiRealtimeModel,
      voice: config.openaiVoice,
      instructions_chars: buildInstructionPreview(ctx.payload),
    });
    return runRealtimeCallSession({ ...ctx, debug });
  }

  debug.session_type = 'stub';

  if (config.workerMode === 'realtime' && !config.openaiApiKey) {
    addWarning(
      debug,
      'WORKER_MODE=realtime but OPENAI_API_KEY is empty in worker process — falling back to STUB (no spoken audio)',
    );
    logError(
      'OPENAI_API_KEY missing in worker environment. ' +
        'Ensure OPENAI_API_KEY is in Laravel .env and restart: php artisan whatsappcall:worker',
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
