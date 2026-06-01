import dotenv from 'dotenv';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
dotenv.config({ path: join(__dirname, '..', '.env'), override: false });

export const WORKER_VERSION = '1.4.0';

const openaiKey = process.env.OPENAI_API_KEY || '';
const workerModeEnv = (process.env.WORKER_MODE || process.env.WHATSAPP_AI_WORKER_MODE || '').toLowerCase();
/** Default realtime — each call supplies company OpenAI key from Laravel; env key is optional fallback. */
const workerMode = workerModeEnv || 'realtime';

export const config = {
  version: WORKER_VERSION,
  port: parseInt(process.env.PORT || '8787', 10),
  host: process.env.HOST || '127.0.0.1',
  laravelAppUrl: (process.env.LARAVEL_APP_URL || 'http://127.0.0.1:8000').replace(/\/$/, ''),
  laravelCallbackUrl: (process.env.WHATSAPP_AI_LARAVEL_CALLBACK_URL || process.env.LARAVEL_CALLBACK_URL || '')
    .replace(/\/$/, ''),
  workerSecret: process.env.WHATSAPP_AI_WORKER_SECRET || '',
  workerMode,
  stubCallDurationMs: parseInt(process.env.STUB_CALL_DURATION_MS || '15000', 10),
  fetchTimeoutMs: parseInt(process.env.WORKER_FETCH_TIMEOUT_MS || '60000', 10),
  openaiApiKey: openaiKey,
  openaiRealtimeModel: process.env.OPENAI_REALTIME_MODEL || 'gpt-realtime',
  openaiVoice: process.env.OPENAI_REALTIME_VOICE || 'alloy',
  openaiTranscriptionModel: process.env.OPENAI_TRANSCRIPTION_MODEL || 'whisper-1',
  openaiTemperature: parseFloat(process.env.OPENAI_REALTIME_TEMPERATURE || '0.7'),
  openaiMaxOutputTokens: process.env.OPENAI_REALTIME_MAX_OUTPUT_TOKENS || '4096',
  openaiVadThreshold: parseFloat(process.env.OPENAI_VAD_THRESHOLD || '0.5'),
  openaiVadPrefixMs: parseInt(process.env.OPENAI_VAD_PREFIX_MS || '300', 10),
  openaiVadSilenceMs: parseInt(process.env.OPENAI_VAD_SILENCE_MS || '700', 10),
  openaiAudioRate: 24000,
  webrtcAudioRate: parseInt(process.env.WEBRTC_AUDIO_RATE || '48000', 10),
  maxCallDurationMs: parseInt(process.env.WHATSAPP_AI_MAX_CALL_MS || '900000', 10),
  remoteTrackTimeoutMs: parseInt(process.env.REMOTE_TRACK_TIMEOUT_MS || '30000', 10),
};
