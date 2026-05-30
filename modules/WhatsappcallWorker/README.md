# WhatsappcallWorker module

Built-in **Node.js** media worker for the `Whatsappcall` module. Answers WhatsApp voice calls via WebRTC and posts call briefs back to Laravel.

## Requirements

- Node.js 18+
- `Whatsappcall` module enabled
- `WHATSAPP_AI_WORKER_SECRET` set in `.env` (same value in Laravel and worker)
- Meta WhatsApp Calling enabled

## Quick start

```bash
# 1. Enable module in Convocon (Modules → WhatsApp Call AI Worker)

# 2. .env
WHATSAPP_AI_WORKER_SECRET=your-random-secret
WHATSAPP_AI_WORKER_PORT=8787

# 3. Install & run worker
php artisan whatsappcall:worker --install

# 4. In another terminal: Laravel app running
php artisan serve

# 5. WhatsApp Calling Setup
#    - Inbound: AI voice agent
#    - Check "Use built-in worker"
#    - Save
```

## Commands

| Command | Description |
|---------|-------------|
| `php artisan whatsappcall:worker` | Start worker (foreground) |
| `php artisan whatsappcall:worker --install` | `npm install` then start |

## Worker modes

| `WORKER_MODE` / `WHATSAPP_AI_WORKER_MODE` | `realtime` (default when `OPENAI_API_KEY` set) or `stub` |
| `realtime` | **Spoken AI** via OpenAI Realtime WebSocket + WebRTC audio bridge |
| `stub` | Connects call silently, posts text brief after ~15s (dev only) |

Set in `.env`:

```env
OPENAI_API_KEY=sk-...
WHATSAPP_AI_WORKER_MODE=realtime
OPENAI_REALTIME_MODEL=gpt-realtime
OPENAI_REALTIME_VOICE=alloy
```

## Production

Run under **Supervisor** or **systemd**, not inside PHP:

```ini
[program:whatsappcall-worker]
command=php /var/www/convocon/artisan whatsappcall:worker
directory=/var/www/convocon
autostart=true
autorestart=true
```

Or run `npm start` directly in `modules/WhatsappcallWorker/worker` with the same env vars as `StartWorkerCommand`.

## API

- `GET /health` — worker status
- `POST /incoming` — Laravel dispatch (see `modules/Whatsappcall/AI_VOICE_WORKER.md`)

## Layout

```text
modules/WhatsappcallWorker/
  Console/StartWorkerCommand.php
  worker/
    src/server.js
    src/incomingHandler.js
    src/webrtcSession.js
    src/stubAiSession.js
    src/laravelClient.js
```
