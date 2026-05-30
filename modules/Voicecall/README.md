# Voice Call (Telnyx / Twilio)

AI phone assistant: greeting, one speech turn, call brief in contact chat.

## Setup

1. Enable **Voicecall** in Apps.
2. **App Settings → Telephony**: choose **Telnyx** (default) or **Twilio**, fill credentials.
3. **Voice Call setup** (`/voicecall/settings`): add your inbound number(s), flow, catalogs, greeting.

### Telnyx

- **TELNYX_API_KEY**, **TELNYX_CONNECTION_ID**, **TELNYX_FROM_NUMBER** in App Settings.
- Telnyx Portal → Voice → Call Control Application → Webhook URL:
  - `https://your-app.com/webhook/voicecall/telnyx` (POST)
- Assign your number to that connection.

### Twilio (legacy)

- **TWILIO_ACCOUNT_SID**, **TWILIO_AUTH_TOKEN** in App Settings.
- Phone number voice webhooks:
  - Incoming: `https://your-app.com/webhook/voicecall/twilio/incoming`
  - Status: `https://your-app.com/webhook/voicecall/twilio/status`

SMS uses the same `telephony_provider` via **Smswpbox**.

## Migrations

```bash
php artisan migrate
```

Adds `provider` and `provider_call_id` on voice tables when not present.
