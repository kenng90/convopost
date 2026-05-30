# WhatsApp AI voice worker integration

Laravel (`Whatsappcall`) handles routing, Meta API proxying, call briefs, and agent UI.

The **built-in worker** lives in the `WhatsappcallWorker` module (`modules/WhatsappcallWorker/worker`).

## Built-in worker (recommended)

```bash
# .env
WHATSAPP_AI_WORKER_SECRET=your-secret
WHATSAPP_AI_WORKER_PORT=8787
# Worker callbacks must hit LOCAL Laravel — not ngrok
WHATSAPP_AI_LARAVEL_CALLBACK_URL=http://127.0.0.1:8000

php artisan whatsappcall:worker --install
```

**Important:** `APP_URL` can stay as your ngrok URL (for Meta webhooks).  
`WHATSAPP_AI_LARAVEL_CALLBACK_URL` must be where `php artisan serve` runs (usually `http://127.0.0.1:8000`).  
The Node worker runs on your machine and cannot reliably call back through ngrok (`ETIMEDOUT`).

In **WhatsApp Calling Setup**:

1. Inbound call handling → **AI voice agent** (or AI after hours)
2. Enable **Use built-in worker**
3. Set **Worker secret** to match `.env`
4. Save

Default URL: `http://127.0.0.1:8787`

See `modules/WhatsappcallWorker/README.md` for production (Supervisor/systemd).

## External worker

Point **AI worker URL** to any service that implements the same HTTP contract below.

## Laravel → worker dispatch

```
POST {WORKER_URL}/incoming
Header: X-Worker-Secret: <secret>
```

```json
{
  "call_id": 123,
  "wa_call_id": "wacid...",
  "company_id": 1,
  "contact_id": 45,
  "wa_user_id": "15551234567",
  "offer": { "type": "offer", "sdp": "..." },
  "required_field_keys": ["name", "email"],
  "ai_greeting": "Hello, how can I help?",
  "handoff_phrases": ["speak to a person", "human agent"],
  "worker_callback_base": "https://your-app.com/api/whatsappcall/worker",
  "callback_secret": "your-secret"
}
```

## Worker → Laravel callbacks

All require `X-Worker-Secret`.

| Method | Path | Body |
|--------|------|------|
| GET | `/api/whatsappcall/worker/calls/{call_id}` | — |
| POST | `/api/whatsappcall/worker/calls/{call_id}/pre-accept` | `{ "sdp": "..." }` |
| POST | `/api/whatsappcall/worker/calls/{call_id}/accept` | `{ "sdp": "..." }` |
| POST | `/api/whatsappcall/worker/calls/{call_id}/terminate` | — |
| POST | `/api/whatsappcall/worker/calls/{call_id}/complete` | see below |

### Complete payload

```json
{
  "duration_seconds": 120,
  "transcript": "optional full transcript",
  "handoff_requested": true,
  "handoff_reason": "Customer asked for a person",
  "structured": {
    "intent": "support",
    "urgency": "medium",
    "summary_bullets": ["..."],
    "fields": [
      {
        "key": "email",
        "label": "Email",
        "value": "jane@example.com",
        "status": "confirmed",
        "source_quote": "my email is jane@example.com"
      }
    ],
    "missing_required": ["phone"]
  }
}
```

Field `status`: `confirmed` | `inferred` | `missing` | `corrected`.

## Test without a live call

```bash
php artisan migrate
php artisan whatsappcall:simulate-ai-complete {call_id} --handoff
```

## Health check

```bash
curl http://127.0.0.1:8787/health
```
