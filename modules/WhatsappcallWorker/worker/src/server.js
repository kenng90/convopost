import express from 'express';
import { config, WORKER_VERSION } from './config.js';
import { handleIncoming, getActiveSessionCount } from './incomingHandler.js';
import { logStartupConfig, logInfo } from './logger.js';

const app = express();
app.use(express.json({ limit: '2mb' }));

logStartupConfig(config);

app.get('/health', (_req, res) => {
  res.json({
    ok: true,
    service: 'convocon-whatsappcall-worker',
    version: WORKER_VERSION,
    mode: config.workerMode,
    realtime_model: config.openaiRealtimeModel,
    openai_configured: Boolean(config.openaiApiKey),
    per_company_openai_keys: true,
    active_sessions: getActiveSessionCount(),
  });
});

app.post('/incoming', async (req, res) => {
  try {
    const secret = config.workerSecret;
    if (secret) {
      const provided = req.headers['x-worker-secret'] || '';
      if (provided !== secret) {
        return res.status(401).json({ ok: false, error: 'Invalid worker secret' });
      }
    }

    const result = handleIncoming(req.body);
    return res.status(202).json(result);
  } catch (err) {
    console.error('[POST /incoming]', err);
    return res.status(500).json({ ok: false, error: err.message });
  }
});

app.listen(config.port, config.host, () => {
  const callbackBase = config.laravelCallbackUrl || config.laravelAppUrl;
  logInfo('Worker HTTP server listening', {
    url: `http://${config.host}:${config.port}`,
    mode: config.workerMode,
    laravel_callbacks: `${callbackBase}/api/whatsappcall/worker`,
  });
  if (/ngrok/i.test(callbackBase)) {
    console.warn(
      'WARNING: Laravel callback URL looks like ngrok. Use WHATSAPP_AI_LARAVEL_CALLBACK_URL=http://127.0.0.1:8000 instead.',
    );
  }
});
