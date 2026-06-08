# Convocon Client Onboarding Procedure Manual

This manual describes the **recommended order** to fully onboard a client onto Convocon — a multi-tenant WhatsApp CRM — so they can use every feature included in their plan.

**Audiences**

| Role | Who | Responsibility |
|------|-----|----------------|
| **Platform operator** | SaaS admin (`admin` role) | Activate platform, install modules, configure billing, assign plans |
| **Client owner** | Business owner (`owner` role) | WhatsApp setup, workspace config, team, automations |
| **Staff agent** | Support/sales staff (`staff` role) | Day-to-day inbox work after owner completes setup |

---

## Overview: Onboarding phases

```
Phase 0  Platform setup (operator)
Phase 1  Account & organization
Phase 2  Plan & billing
Phase 3  WhatsApp Cloud API (critical path)
Phase 4  Workspace & team
Phase 5  Contacts & audience
Phase 6  Inbox & messaging basics
Phase 7  Automations & commerce
Phase 8  Add-ons & integrations
Phase 9  Insights & go-live
```

**Golden rule:** Nothing that sends or receives WhatsApp messages works until **Phase 3** is complete (`whatsapp_webhook_verified = yes` and `whatsapp_settings_done = yes`).

---

## Phase 0 — Platform setup (operator only)

Complete these steps **before** inviting the first paying client.

### 0.1 Activate the application

- [ ] Run migrations and seed roles (`admin`, `owner`, `client`, `staff`)
- [ ] Ensure `storage/activation` exists (or demo mode is enabled) so users are not blocked by the activation middleware

### 0.2 Install and enable modules

**Navigation:** Admin → **Apps** (`admin.apps.index`)

- [ ] Install all modules the business will sell (core: **Wpbox**, **Contacts**, **Agents**, **Flowmaker**)
- [ ] Install billing modules if pricing is enabled: **StripehSubscribe** and/or **PaystackSubscribe**
- [ ] Install optional modules per plan tier (catalog, flows, calls, journeys, etc.)

### 0.3 Configure site settings

**Navigation:** Admin → **Site Settings** (`admin.settings.index`)

| Tab | Key items |
|-----|-----------|
| **Setup** | `APP_NAME`, `APP_URL`, storage (local/S3), OAuth (Google/Facebook), multi-org toggle |
| **Finances** | Enable pricing, subscription processor, Stripe/Paystack keys, free plan ID, credits |
| **Apps & Plugins** | Pusher (real-time chat), Recaptcha, module global fields |

- [ ] Set `APP_URL` to the production domain (webhook URLs depend on this)
- [ ] Configure subscription processor if `ENABLE_PRICING=true`
- [ ] If using Facebook Embedded Signup: set `EMBEDDED_FB_CONFIG_ID` and enable **Embeddedlogin** module

### 0.4 Create pricing plans

**Navigation:** Admin → **Pricing plans** (`plans.index`)

- [ ] Create or seed plans (Starter / Growth / Pro / Agency — see `config/plan-entitlements.php`)
- [ ] Assign correct **plugins** and **capabilities** to each plan
- [ ] Set usage limits (contacts, messages, campaigns, agents, integrations)

### 0.5 Optional: Admin onboarding checklist

**Navigation:** Admin → **Dashboard**

Configure env tasks `TASK_1` … `TASK_6` so operators track platform readiness.

---

## Phase 1 — Account & organization

### 1.1 Create the client account

**Options (pick one):**

| Method | Result |
|--------|--------|
| Self-registration | Creates `owner` user + new **Company** automatically |
| Admin creates company | Admin → **Companies** → create/activate |
| Social login (Google/Facebook) | Creates owner + company if new user |
| Company-page signup | User joins existing company as `client` |

- [ ] Owner verifies email (if required)
- [ ] Owner completes profile (name, phone)

### 1.2 Activate the company (operator)

**Navigation:** Admin → **Companies** (`admin.companies.index`)

- [ ] Activate company (`admin.company.activate`)
- [ ] Assign plan if not self-selected
- [ ] Assign allowed apps/plugins (`admin.company.updateApps`) if overriding plan defaults

### 1.3 Multi-organization (Agency / Growth with extra orgs)

If `ENABLE_MULTI_ORGANIZATIONS=true`:

- [ ] Owner adds organizations via **Workspace** (plan-limited by `limit_companies`)
- [ ] Each organization gets its own WhatsApp number and settings

---

## Phase 2 — Plan & billing

Skip if pricing is disabled.

**Navigation:** Setup → **Workspace** → **Plan & billing** (`plans.current`)

- [ ] Owner selects and subscribes to a plan (`plans.subscribe`)
- [ ] Confirm plan unlocks expected menus (see **Plan feature matrix** below)
- [ ] Set up billing portal if using Stripe (`billing` route)
- [ ] Review usage meters: contacts, messages, campaigns, agent seats, integrations

**Important:** Routes and menus are gated by `plan.plugin:*` and `plan.capability:*` middleware. Features not on the plan will not appear.

---

## Phase 3 — WhatsApp Cloud API setup (critical path)

The app **auto-redirects** owners to setup until both flags are set:

- `whatsapp_webhook_verified = yes`
- `whatsapp_settings_done = yes`

**Navigation:** Setup → **WhatsApp & team** → **Cloud API Setup** (`whatsapp.setup`)

### Path A — Facebook Embedded Signup (recommended if available)

**When:** **Embeddedlogin** module enabled + `EMBEDDED_FB_CONFIG_ID` configured

- [ ] Owner completes Facebook OAuth embedded flow
- [ ] System auto-registers phone number and subscribes webhook
- [ ] Confirm verification badge on setup page

### Path B — Manual setup (3 steps)

#### Step 1 — Facebook app & webhook

- [ ] Create Meta Developer account and Facebook app ([Meta docs](https://developers.facebook.com/docs/whatsapp/cloud-api/get-started))
- [ ] Add **WhatsApp** product to the app
- [ ] In WhatsApp → Configuration, enter:
  - **Callback URL:** `{APP_URL}/webhook/wpbox/receive/{verify_token}`
  - **Verify token:** shown on setup page (Sanctum personal access token)
- [ ] Subscribe webhook field: **messages**
- [ ] Confirm green “Webhook verified” alert on setup page

#### Step 2 — Permanent access token

- [ ] Generate permanent access token in Meta Business settings
- [ ] Paste into setup form

#### Step 3 — Phone & business account IDs

- [ ] Enter **Phone Number ID**
- [ ] Enter **WhatsApp Business Account ID**
- [ ] Save settings (`whatsapp.store`)

### Post-setup verification

- [ ] Send a test inbound WhatsApp message → appears in **Inbox → Chat**
- [ ] Send a test outbound reply from chat
- [ ] (Optional) Configure **WhatsApp Calling** card on same page → **Call settings** (`whatsappcall.settings`) if on Pro+ plan

**Company credentials** (also editable later):

**Navigation:** Setup → **Workspace** → **Apps** (`admin.apps.company`)

- `facebook_app_id`, `facebook_app_secret`
- `whatsapp_phone_number_id`, `whatsapp_business_account_id`, `whatsapp_permanent_access_token`

---

## Phase 4 — Workspace & team

### 4.1 Company profile

**Navigation:** Setup → **Workspace** → **Company** (`admin.companies.edit`)

- [ ] Company name, logo, branding
- [ ] Phone, subdomain, public share link (`admin.share`)

### 4.2 Per-app integration settings

**Navigation:** Setup → **Workspace** → **Apps** (`admin.apps.company`)

Review and configure **vendor fields** for every enabled module (SMTP, Twilio, Shopify, M-Pesa, OpenRouter, etc.). Do this **before** enabling dependent features in later phases.

### 4.3 Add agents (staff users)

**Navigation:** Setup → **WhatsApp & team** → **Agents** (`agent.index`)

- [ ] Create staff accounts (role: `staff`)
- [ ] Stay within plan agent limit (`limit_agents`)
- [ ] Configure agent behavior:
  - `agent_enable` — allow agents to use inbox
  - `agent_assigned_only` — restrict to assigned conversations only

- [ ] Send login credentials to each agent
- [ ] Agents log in and confirm they see **Chat** menu

**Order note:** WhatsApp setup (Phase 3) should be done first; agents cannot meaningfully use the inbox until messages flow.

---

## Phase 5 — Contacts & audience

**Navigation:** Audience → **Contacts**

| Step | Menu | Route | Action |
|------|------|-------|--------|
| 5.1 | Fields | `contacts.fields.index` | Define custom contact fields |
| 5.2 | Groups | `contacts.groups.index` | Create segments (e.g. VIP, Leads, Support) |
| 5.3 | Import | `contacts.import.index` | Bulk import CSV/spreadsheet |
| 5.4 | Contact list | `contacts.index` | Verify records; add manually if needed |

- [ ] Confirm contact count is within plan limit (`limit_orders`)
- [ ] Assign test contacts to groups for campaign/flow testing

**Chat sidebar:** Contact panel and Notes appear automatically in inbox when **Contacts** module is active.

---

## Phase 6 — Inbox & messaging basics

### 6.1 Templates (required for campaigns & outbound template messages)

**Navigation:** Setup → **WhatsApp & team** → **Templates** (`templates.index`)

- [ ] Sync templates from Meta (requires Facebook app credentials)
- [ ] Create/submit new templates for approval
- [ ] Wait for Meta approval before using in campaigns

### 6.2 Triggers (auto-replies)

**Navigation:** Setup → **WhatsApp & team** → **Triggers** (`replies.index`)

- [ ] Create keyword/welcome triggers
- [ ] Test with a WhatsApp message to your business number

### 6.3 Inbox workflow

**Navigation:** Inbox → **Chat** (`chat.index`)

- [ ] Confirm real-time updates (Pusher configured in site settings)
- [ ] Test assignment/handover between agents
- [ ] Review chat sidebar apps (Notes, Contact, store links, etc.)

### 6.4 API access (Pro+ / Agency)

**Navigation:** Setup → **WhatsApp & team** → **API info** / **API campaigns**

- [ ] Copy API token and webhook URLs
- [ ] Test `/api/wpbox/*` endpoints if integrating external systems

---

## Phase 7 — Automations & commerce

Complete subsections **matching the client’s plan tier**.

### 7.1 Flow builder (Starter+)

**Navigation:** Automations & commerce → **Flow builder** (`flows.index`)

- [ ] Build first bot flow (welcome, FAQ, handover to agent)
- [ ] Publish and link to a trigger or template button
- [ ] If using **AI nodes:** configure OpenRouter API key in **Workspace → Apps** (see `modules/Flowmaker/README_LLM_SETUP.md`)

### 7.2 Campaigns (Growth+)

**Navigation:** Outbound → **Campaigns** (`campaigns.index`)

**Requires:** approved templates + contact groups

- [ ] Create broadcast to a test group
- [ ] Schedule or send immediately
- [ ] Monitor delivery in reports

### 7.3 Product catalog (Growth+)

**Navigation:** Automations & commerce → **Product catalogs** (`catalogs.page`)

- [ ] Enable `whatsapp_catalog_enabled` in Apps settings
- [ ] Add catalog items (within `limit_catalog_items`)
- [ ] Share catalog link `/catalog/{id}` or send via chat

### 7.4 WhatsApp Flows / forms (Pro+)

**Navigation:** Automations & commerce → **WhatsApp forms** (`whatsapp-flows.index`)

- [ ] Enable `whatsapp_flows_enabled`
- [ ] Build and publish a form
- [ ] Webhook: `{APP_URL}/webhook/wpbox/flows/{token}`
- [ ] Review submissions: Insights → **Reports** → Form submissions

### 7.5 Payments (Pro+)

**Navigation:** Workspace → **Apps** → Flowmaker M-Pesa fields

- [ ] Configure Daraja credentials (sandbox then production)
- [ ] Add payment nodes in flows
- [ ] Test STK push via **Invoice** module API

---

## Phase 8 — Add-ons & integrations

Configure only modules included in the client’s plan.

### 8.1 Store integrations (Growth+, max per `limit_integrations`)

| Store | Sidebar in chat | Config (Workspace → Apps) |
|-------|-----------------|---------------------------|
| **Shopify** | Shopify | `shopify_store_name`, `shopify_access_token`, API version |
| **WooCommerce** | WooCommerce | Store URL, consumer key/secret |

- [ ] Connect store credentials
- [ ] Test product link fetch in a chat conversation

### 8.2 Website chat widget (Growth+)

**Navigation:** Add-ons → **Web Widget** (`embedwhatsapp.edit`)

- [ ] Customize widget appearance and pre-filled message
- [ ] Embed script on client website
- [ ] Test click-to-WhatsApp flow

### 8.3 Journey pipelines (Pro+)

**Navigation:** Add-ons → **Journies** (`journies.index`)

- [ ] Create pipeline stages (e.g. Lead → Qualified → Won)
- [ ] Move test contacts through stages
- [ ] Use **Contact journeys** sidebar in chat

### 8.4 Reminders & reservations (Pro+)

**Navigation:** Add-ons → **Reminders**

- [ ] Enable `ENABLE_REMINDERS` in Apps settings
- [ ] Create reminder templates
- [ ] Test booking flow; confirm **Reservations** sidebar in chat

### 8.5 Knowledge base / help center (Pro+)

**Navigation:** Add-ons → **Help Center**

- [ ] Configure KB heading and widget settings
- [ ] Publish articles
- [ ] Verify public URL: `/knowledge/{company_alias}`

### 8.6 Multi-channel (optional modules)

| Channel | Module | Setup |
|---------|--------|-------|
| **Email** | Emailwpbox | SMTP + message templates in Apps |
| **SMS** | Smswpbox | Twilio SID, token, from number |
| **Voice (PSTN)** | Voicecall | Telnyx or Twilio in Apps → Voice Call settings |
| **WhatsApp voice** | Whatsappcall | Calling settings + Infobip WebRTC |
| **AI voice worker** | WhatsappcallWorker | Requires Whatsappcall + Node worker (see module README) |

### 8.7 Custom domain (optional)

**Navigation:** Workspace → **Apps** → **Domain** module

- [ ] Point DNS to application
- [ ] Verify domain for white-label access

---

## Phase 9 — Insights & go-live

### 9.1 Reports & dashboards

**Navigation:** Insights → **Reports**

| Report | Route | Purpose |
|--------|-------|---------|
| Overview | `reports.index` | Conversation volume, agent activity |
| Transactions | `reports.dashboard` | Payments and commerce |
| Form submissions | `whatsapp-flows.responses` | WhatsApp Flow responses |

- [ ] Review dashboard widgets on home (`dashboard`)
- [ ] Set baseline metrics before go-live

### 9.2 Go-live checklist

- [ ] WhatsApp webhook verified and settings complete
- [ ] At least one approved template (if using campaigns)
- [ ] Agents trained on inbox, assignment, and notes
- [ ] Welcome trigger or flow active
- [ ] Contact import complete
- [ ] Plan limits understood (messages, contacts, campaigns)
- [ ] Billing active and receipt confirmed
- [ ] (Optional) Widget live on website
- [ ] (Optional) API documented for client’s dev team

### 9.3 Post go-live (first 7 days)

- [ ] Monitor failed messages and template rejections
- [ ] Review agent response times in reports
- [ ] Adjust triggers/flows based on real conversations
- [ ] Scale agents if approaching seat limits

---

## Plan feature matrix

Use this to set client expectations and skip unavailable phases.

| Feature | Starter | Growth | Pro | Agency |
|---------|:-------:|:------:|:---:|:------:|
| Team inbox | ✓ | ✓ | ✓ | ✓ |
| Contacts CRM | ✓ | ✓ | ✓ | ✓ |
| Flow builder | ✓ | ✓ | ✓ | ✓ |
| Campaigns | — | ✓ | ✓ | ✓ |
| Product catalog | — | ✓ | ✓ | ✓ |
| Shopify / WooCommerce | — | ✓ (1 store) | ✓ | ✓ |
| Web widget | — | ✓ | ✓ | ✓ |
| WhatsApp Flows | — | — | ✓ | ✓ |
| WhatsApp voice | — | — | ✓ | ✓ |
| Journeys | — | — | ✓ | ✓ |
| Reminders | — | — | ✓ | ✓ |
| Knowledge base | — | — | ✓ | ✓ |
| Reports | — | — | ✓ | ✓ |
| API access | — | — | ✓ | ✓ |
| Multi-org / unlimited | — | 3 orgs | 15 agents | Unlimited |

**Typical limits**

| Tier | Agents | Contacts | Messages/mo | Campaigns/mo |
|------|--------|----------|-------------|--------------|
| Starter | 3 | 500 | 1,000 | — |
| Growth | 5 | 5,000 | 5,000 | 10 |
| Pro | 15 | Unlimited | Unlimited | Unlimited |
| Agency | Unlimited | Unlimited | Unlimited | Unlimited |

---

## Dependency reference

What must exist before what:

```
Platform activated
    └── Plan assigned
            └── WhatsApp Cloud API configured
                    ├── Templates synced
                    ├── Triggers / Flows
                    ├── Campaigns (needs templates + groups)
                    ├── Catalog (needs WhatsApp + enable flag)
                    └── API campaigns (needs API capability)

Agents created
    └── Staff can use inbox

Contacts imported
    └── Campaigns & segments

Facebook app credentials
    └── Template sync & some Meta features

Flowmaker + OpenRouter key
    └── AI nodes in flows

M-Pesa Daraja config
    └── In-flow payments & Invoice STK

Whatsappcall module
    └── WhatsappcallWorker (AI voice)

Shopify/Woo credentials
    └── Product links in chat sidebar
```

---

## Troubleshooting quick reference

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| Redirected to setup on every page | Webhook or settings incomplete | Complete Phase 3; check both config flags |
| Webhook not verifying | Wrong URL/token or `APP_URL` mismatch | Copy exact callback URL and verify token from setup page |
| Templates empty | Missing Facebook app ID/secret | Set in Workspace → Apps |
| Menu item missing | Plan plugin/capability | Upgrade plan or admin assigns plugins |
| Campaign blocked | No approved template or over limit | Sync templates; check plan meters |
| Demo company (ID 1) cannot open setup | Demo restriction | Register a real account |
| Chat not updating live | Pusher not configured | Admin Site Settings → Pusher keys |

---

## Recommended onboarding timeline

| Day | Phases | Outcome |
|-----|--------|---------|
| **Day 0** | 0–2 (operator + account + plan) | Client can log in with correct plan |
| **Day 1** | 3–4 | WhatsApp live; team invited |
| **Day 2** | 5–6 | Contacts imported; templates & triggers active |
| **Day 3–5** | 7 | Flows, campaigns, catalog per plan |
| **Day 5–7** | 8–9 | Integrations, widget, reports; go-live |

Adjust pace by plan complexity — **Starter** clients may go live after Day 2; **Pro/Agency** clients need the full week for voice, journeys, and integrations.

---

*Document version: 1.0 — aligned with Convocon module structure and plan entitlements.*
