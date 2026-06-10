/** Per-call diagnostics returned to Laravel on complete. */

export function createSessionDebug(sessionType) {
  return {
    session_type: sessionType,
    worker_mode: null,
    openai_configured: false,
    openai_model: null,
    openai_connected: false,
    openai_session_ready: false,
    initial_greeting_sent: false,
    remote_track_received: false,
    peer_connection_state: null,
    audio_in_frames: 0,
    audio_out_chunks: 0,
    openai_event_counts: {},
    errors: [],
    warnings: [],
    started_at: new Date().toISOString(),
    ended_at: null,
  };
}

export function bumpEvent(debug, type) {
  debug.openai_event_counts[type] = (debug.openai_event_counts[type] || 0) + 1;
}

export function addError(debug, message) {
  debug.errors.push({ at: new Date().toISOString(), message: String(message) });
}

export function addWarning(debug, message) {
  debug.warnings.push({ at: new Date().toISOString(), message: String(message) });
}

export function finalizeDebug(debug) {
  debug.ended_at = new Date().toISOString();
  return debug;
}
