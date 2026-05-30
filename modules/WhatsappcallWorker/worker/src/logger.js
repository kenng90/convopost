/** Structured console logging for the WhatsApp AI worker. */

let callId = null;

export function setCallContext(id) {
  callId = id ?? null;
}

export function clearCallContext() {
  callId = null;
}

function prefix() {
  const ts = new Date().toISOString();
  const cid = callId != null ? ` call=${callId}` : '';
  return `[${ts}]${cid}`;
}

export function log(level, message, data = undefined) {
  const line = `${prefix()} [${level}] ${message}`;
  if (data === undefined) {
    console.log(line);
    return;
  }
  console.log(line, data);
}

export function logInfo(message, data) {
  log('INFO', message, data);
}

export function logWarn(message, data) {
  log('WARN', message, data);
}

export function logError(message, data) {
  log('ERROR', message, data);
}

export function logDebug(message, data) {
  if (process.env.WORKER_DEBUG === '0') return;
  log('DEBUG', message, data);
}

export function maskSecret(value) {
  if (!value || typeof value !== 'string') return '(empty)';
  if (value.length <= 8) return '***';
  return `${value.slice(0, 7)}...${value.slice(-4)} (${value.length} chars)`;
}

export function logStartupConfig(cfg) {
  logInfo('Worker configuration', {
    version: cfg.version,
    mode: cfg.workerMode,
    openai_key: maskSecret(cfg.openaiApiKey),
    openai_model: cfg.openaiRealtimeModel,
    openai_voice: cfg.openaiVoice,
    laravel_callback: cfg.laravelCallbackUrl || cfg.laravelAppUrl,
    worker_secret_set: Boolean(cfg.workerSecret),
    webrtc_audio_rate: cfg.webrtcAudioRate,
    openai_audio_rate: cfg.openaiAudioRate,
    max_call_ms: cfg.maxCallDurationMs,
  });

  if (cfg.workerMode === 'realtime' && !cfg.openaiApiKey) {
    logError(
      'WORKER_MODE=realtime but OPENAI_API_KEY is missing — calls will fail or fall back to stub. ' +
        'Set OPENAI_API_KEY in Laravel .env and restart via: php artisan whatsappcall:worker',
    );
  }

  if (cfg.workerMode === 'stub') {
    logWarn(
      'WORKER_MODE=stub — no spoken AI. Set WHATSAPP_AI_WORKER_MODE=realtime and OPENAI_API_KEY for voice.',
    );
  }
}
