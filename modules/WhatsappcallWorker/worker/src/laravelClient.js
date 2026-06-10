import { config } from './config.js';
import { logInfo, logError } from './logger.js';

export class LaravelClient {
  constructor(payload) {
    this.callId = payload.call_id;
    this.waCallId = payload.wa_call_id || null;
    this.base = resolveCallbackBase(payload);
    this.secret = payload.callback_secret || config.workerSecret;
    console.log(`[laravel] callbacks → ${this.base}/calls/${this.callId}`);
  }

  headers() {
    const h = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'User-Agent': 'Convocon-WhatsappcallWorker/1.0',
    };
    if (this.secret) {
      h['X-Worker-Secret'] = this.secret;
    }
    if (/ngrok-free\.dev|ngrok\.io/i.test(this.base)) {
      h['ngrok-skip-browser-warning'] = 'true';
    }
    return h;
  }

  async request(method, path, body = null) {
    const url = `${this.base}/calls/${this.callId}${path}`;
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), config.fetchTimeoutMs);

    const options = {
      method,
      headers: this.headers(),
      signal: controller.signal,
    };
    if (body && method !== 'GET') {
      options.body = JSON.stringify(body);
    }

    try {
      const res = await fetch(url, options);
      clearTimeout(timeout);
      const text = await res.text();
      let json = null;
      try {
        json = text ? JSON.parse(text) : null;
      } catch {
        json = { raw: text };
      }
      if (!res.ok) {
        const err = new Error(`Laravel ${method} ${path} failed: ${res.status} → ${url}`);
        err.status = res.status;
        err.body = json;
        logError('Laravel request failed', { method, path, status: res.status, body: json });
        throw err;
      }
      return json;
    } catch (err) {
      clearTimeout(timeout);
      if (err.name === 'AbortError') {
        throw new Error(`Laravel request timed out after ${config.fetchTimeoutMs}ms → ${url}`);
      }
      if (err.cause?.code === 'ETIMEDOUT' || err.message?.includes('fetch failed')) {
        throw new Error(
          `Cannot reach Laravel at ${url}. ` +
            `Set WHATSAPP_AI_LARAVEL_CALLBACK_URL=http://127.0.0.1:8000 in .env ` +
            `(same host/port as php artisan serve). Do not use ngrok for worker callbacks.`,
          { cause: err },
        );
      }
      throw err;
    }
  }

  preAccept(sdp) {
    return this.request('POST', '/pre-accept', this.metaBody({ sdp }));
  }

  accept(sdp) {
    return this.request('POST', '/accept', this.metaBody({ sdp }));
  }

  metaBody(body) {
    if (this.waCallId) {
      body.wa_call_id = this.waCallId;
    }
    return body;
  }

  terminate() {
    return this.request('POST', '/terminate', {});
  }

  complete(data) {
    logInfo('Posting call completion to Laravel', {
      duration_seconds: data.duration_seconds,
      has_worker_debug: Boolean(data.worker_debug),
      session_type: data.worker_debug?.session_type ?? null,
      summary_bullets: data.structured?.summary_bullets?.length ?? 0,
    });
    return this.request('POST', '/complete', data);
  }

  show() {
    return this.request('GET', '');
  }
}

function resolveCallbackBase(payload) {
  if (payload.worker_callback_base) {
    return String(payload.worker_callback_base).replace(/\/$/, '');
  }
  const fromEnv = config.laravelCallbackUrl || config.laravelAppUrl;
  return `${String(fromEnv).replace(/\/$/, '')}/api/whatsappcall/worker`;
}
