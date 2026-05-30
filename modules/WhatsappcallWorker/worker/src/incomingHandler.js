import { randomUUID } from 'crypto';
import { config, WORKER_VERSION } from './config.js';
import { LaravelClient } from './laravelClient.js';
import { createWhatsAppAnswer } from './webrtcSession.js';
import { runAiSession } from './openaiRealtime.js';
import { setCallContext, clearCallContext, logInfo, logError, logWarn, maskSecret } from './logger.js';
import { createSessionDebug } from './sessionDebug.js';
import { buildErrorCompletionPayload, postCallCompletion } from './callCompletion.js';

const activeSessions = new Map();

/**
 * Accept dispatch from Laravel and return immediately.
 * WebRTC + Meta pre-accept/accept run in the background (can take 10–30s).
 */
export function handleIncoming(payload) {
  const sessionId = randomUUID();
  const callId = payload.call_id;
  if (!callId || !payload.offer?.sdp) {
    throw new Error('call_id and offer.sdp are required');
  }

  if (activeSessions.has(callId)) {
    logWarn('Duplicate incoming dispatch ignored', { call_id: callId });
    return { ok: true, session_id: activeSessions.get(callId).sessionId, duplicate: true };
  }

  activeSessions.set(callId, { sessionId, callId, status: 'queued', startedAt: Date.now() });

  setImmediate(() => {
    processIncomingCall(payload, sessionId).catch((err) => {
      logError('Background call processing failed', { message: err.message });
      activeSessions.delete(callId);
      clearCallContext();
    });
  });

  logInfo('Incoming call queued', {
    call_id: callId,
    session_id: sessionId,
    worker_mode: config.workerMode,
    openai_key: maskSecret(config.openaiApiKey),
    flow_id: payload.flow_id,
    sdp_chars: payload.offer?.sdp?.length,
  });

  return {
    ok: true,
    version: WORKER_VERSION,
    session_id: sessionId,
    status: 'queued',
    worker_mode: config.workerMode,
    openai_configured: Boolean(config.openaiApiKey),
  };
}

async function processIncomingCall(payload, sessionId) {
  const callId = payload.call_id;
  setCallContext(callId);
  const laravel = new LaravelClient(payload);
  const startedAt = Date.now();
  let webrtc = null;
  let completionPayload = null;
  const debug = createSessionDebug('unknown');

  const session = activeSessions.get(callId);
  if (session) {
    session.status = 'connecting';
  }

  try {
    logInfo('Creating WebRTC answer', { session_id: sessionId });

    webrtc = await createWhatsAppAnswer(payload.offer.sdp, payload.offer.type || 'offer');
    logInfo('WebRTC answer created', { sdp_chars: webrtc.sdp?.length });

    await laravel.preAccept(webrtc.sdp);
    logInfo('Meta pre-accept OK');

    await laravel.accept(webrtc.sdp);
    logInfo('Meta accept OK — starting AI session', {
      worker_mode: config.workerMode,
      ice_state: webrtc.peerConnection.iceConnectionState,
    });

    if (session) {
      session.status = 'active';
    }

    completionPayload = await runAiSession({
      payload,
      laravel,
      startedAt,
      peerConnection: webrtc.peerConnection,
      audioSource: webrtc.audioSource,
      debug,
    });
  } catch (err) {
    logError('Call processing failed', { message: err.message, stack: err.stack?.split('\n').slice(0, 4) });
    if (!completionPayload) {
      completionPayload = buildErrorCompletionPayload({
        payload,
        startedAt,
        debug,
        errorMessage: err.message,
      });
    }
  } finally {
    if (webrtc) {
      webrtc.cleanup();
    }

    if (!completionPayload) {
      completionPayload = buildErrorCompletionPayload({
        payload,
        startedAt,
        debug,
        errorMessage: 'Call ended without session completion payload',
      });
    }

    const posted = await postCallCompletion(laravel, completionPayload);
    if (!posted) {
      logError('Failed to save call summary to Laravel — check worker secret and callback URL');
    }

    activeSessions.delete(callId);
    clearCallContext();
  }
}

export function getActiveSessionCount() {
  return activeSessions.size;
}
