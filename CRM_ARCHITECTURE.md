# ConvoCon CRM architecture

Extracted from the live Laravel 10 codebase (7 Sep 2026).

The CRM is not a module. It is a **conversation-centric contact hub**: the person who messages you is the record, the WhatsApp inbox is the workspace, and **Journeys** (Kanban stages) stand in for deals.

There is no `Deal`, `Opportunity`, `Lead`, or `Pipeline` Eloquent model. A “deal” is a row in `journey_stage_contacts`. Pipeline value is enrollment count multiplied by average paid invoice amount.

---

## 1. As-built system map

ConvoCon sells WhatsApp operations. The person who messages you is the record. Staff work that record in the inbox. Journeys move the same person across named stages and can fire a WhatsApp campaign on entry. Invoices and bookings write back into those stages.

```
Capture          Contact           Workspace         Pipeline          Monetize         Sync
Channels    →    CRM record    →   Inbox · assign →  Journeys      →  Invoice      →  Webhooks
Import · API                                          stages           Booking          HubSpot
```

### Where the code lives

| Concern | Home | What it owns |
|---|---|---|
| Person record | `modules/Contacts` | contacts, groups, custom fields, import/export, merge, opt-in |
| Messaging overlay | `modules/Wpbox/Models/Contact` | messages, notes, conversations, send/reply, AI bot flag, assignment |
| Flow overlay | `modules/Flowmaker/Models/Contact` | per-contact flow variables and state |
| Pipeline | `modules/Journies` | journeys, stages, activities, group rules, kanban, sidebar |
| Staff assignment | `contacts.user_id` + `modules/Agents` | inbox assign, `AssignAgent` node, assigned-only filter |
| 360 view / revenue | `app/Services/Platform` | `Customer360Service`, `RevenueDashboardService`, `InvoicePaidSyncService` |
| Playbooks | `app/Services/Outcomes` | Lead-to-Cash, Cart Recovery, Booking Convert installers |

### Composition, not a CRM product

`modules/Contacts/module.json` has no menus. Contact list, fields, groups, and import are declared on **Wpbox**. Journeys is the only module that advertises itself as CRM (`keywords: journeys, pipeline, kanban, crm`). The public landing page calls this “Kanban CRM” and “Contact CRM” — product language that the module graph does not match.

**Tenant isolation** is `company_id` plus `App\Scopes\CompanyScope` when `session('company_id')` is set. Public API scopes by token company. Jobs that import or send campaigns must restore the session or use `withoutGlobalScopes()`. This is not row-level database policy. `countries` is global reference data.

**Classic CRM objects that do not exist:** accounts/companies-as-customers, deals, opportunities, activities calendar, tasks, quotes. Invoice is commerce, not a CRM opportunity. Groups are tags. Notes are Wpbox messages with `is_note`. HubSpot sync creates a contact by email only. No Salesforce or Pipedrive adapters.

---

## 2. Domain

### Contact inheritance is the domain leak

CRM fields live on `Modules\Contacts\Models\Contact`. Messaging and automation subclass it instead of composing against it. Every consumer that needs send/reply or flow state imports a different class for the same `contacts` row.

| Class | Extends | Extra behavior |
|---|---|---|
| `Modules\Contacts\Models\Contact` | Eloquent `Model` | groups, fields, country, `channelIdentities`, webhooks, `CompanyScope` |
| `Modules\Wpbox\Models\Contact` | `Contacts\Contact` | messages, notes, conversations, `sendMessage`, `botReply`, `addNote` |
| `Modules\Flowmaker\Models\Contact` | `Wpbox\Contact` | `contactState`, `setContactState`, `changeVariables` |

There is no `app/Models/Contact.php`.

Base Contact uses `public $guarded = []` and defines no `$casts`. SoftDeletes is on. Creating sets `company_id` from session when missing. Phone → country is a prefix walk on create. Name/email/phone changes dispatch `contact.updated` via `PublicWebhookDispatcher`.

### Core tables

| Table | Role | Tenant |
|---|---|---|
| `contacts` | Person: name, phone, email, avatar, `user_id`, `has_chat`, `resolved_chat`, `subscribed`, `enabled_ai_bot`, `credits`, `language` | `company_id` |
| `groups` | Named segment / tag | `company_id` |
| `groups_contacts` | M2M membership | via contact |
| `custom_contacts_fields` | Field definition (`text`, `number`, `date`, `email`) | `company_id` |
| `custom_contacts_fields_contacts` | Field value on pivot | via contact |
| `contact_imports` | Queued CSV/Excel import job | `company_id` |
| `countries` | Phone prefix lookup | global |
| `journeys` | Named pipeline | `company_id` |
| `journey_stages` | Ordered stage + optional API campaign + delay | via journey |
| `journey_stage_contacts` | Current stage membership (one stage per journey) | via stage |
| `journey_activities` | Audit of add / move / remove + campaign status | `company_id` |
| `journey_group_rules` | When contact joins group → move to stage | `company_id` unique `(group, journey)` |

### Contact columns that are really inbox state

The person table carries conversation workspace flags. That is why Contacts feels like CRM and chat at once.

| Column | CRM meaning | Inbox meaning |
|---|---|---|
| `user_id` | Owner / assignee | Staff who owns the chat |
| `has_chat` | Has messaged | Appears in inbox list |
| `resolved_chat` | Closed conversation | Hidden from open queue |
| `enabled_ai_bot` | Automation allowed | Bot replies on inbound |
| `subscribed` | Marketing opt-in | Campaign audience filter |
| `credits` | Flow `CheckPricing` wallet | Not company billing credits |
| `last_*_reply_at` | Recency | Inbox sort / unread |

### Models that belong to Contact

| Model | Link |
|---|---|
| `ChannelIdentity` | omnichannel handle (WhatsApp, Instagram, Messenger, TikTok) |
| `Conversation` / `ConversationWorkspace` | threaded inbox |
| `ConsentRecord` | opt-in / opt-out |
| `AgentMemory` | AI agent memory (not staff Agents) |
| `OutcomeAttribution` | playbook outcome billing |
| `Reservation` (Reminders) | booking |
| `CatalogCartSession` | open cart |
| `Invoice` | phone match and optional `notes.contact_id` |

### Contacts module surface

Web routes in `modules/Contacts/Routes/web.php` (auth): contact CRUD, merge, bulk remove, subscribe/unsubscribe, assign/remove group; groups CRUD; fields CRUD; import index/store/history/status.

Module `api.php` is a stub — it returns `$request->user()`, not contacts.

Public API v1 (`app/Http/Controllers/Api/V1/ContactsController.php`): `GET/POST /api/v1/contacts`, `GET/PATCH /api/v1/contacts/{contact}`. Uses `Modules\Wpbox\Models\Contact`. Presenter: `id`, `name`, `phone`, `email`, `has_chat`, `resolved`, `assigned_user_id`, `created_at`. Store can sync groups by name and custom fields.

Import: `ProcessContactImportJob` + `ContactsImport` (chunk 150, upsert by phone or social `channel`+`external_id`, auto-creates Fields). Optional post-import `AssignContactImportGroupJob`.

### Journeys module config

Alias `journies`. Vendor fields: `JOURNEYS_ENABLED`, `JOURNEYS_CONFIRM_BEFORE_SEND`, `JOURNEYS_AUTO_ENROLL_NEW_CONTACTS`, `JOURNEYS_DEFAULT_JOURNEY_ID`, `JOURNEYS_STAFF_CAN_MANAGE`.

Sidebar: “Contact journeys”. Report: “Journey pipelines”. Menus: `journies.index` for owner and staff. Plan gate: `plan.plugin:journies`.

---

## 3. Runtime — one write path for the pipeline

Every pipeline change is supposed to go through `JourneyContactService::moveContactToStage`:

1. Company match check.
2. Optional skip if already on the target stage.
3. Detach the contact from **every** stage in that journey (one stage per journey).
4. Attach to the target stage.
5. Write `JourneyActivity` (`added` vs `moved`).
6. If `$fireCampaign` and the stage has `campaign_id`, fire `ContactMovedToStage` → `DispatchJourneyCampaign` → `SendJourneyStageCampaign` (optional `campaign_delay_minutes`) → Wpbox `Campaign::makeMessages` + `CampaignDispatchService::sendSynchronously`.

```
Move sources (11 writers)
        ↓
JourneyContactService::moveContactToStage
        ├── JourneyActivity (added / moved)
        └── ContactMovedToStage
                    ↓
            SendJourneyStageCampaign (queued, tries=3)
                    ↓
            Wpbox campaign (synchronous send)
```

### Writers into `moveContactToStage`

| Source string | Caller | When |
|---|---|---|
| `manual_kanban` | `StagesController::moveContact` | Drag on journey board |
| `manual_sidebar` | `StagesController::moveContactFromSideapp` | Inbox journeys panel |
| `api` | Journies `ApiController` | External token API |
| `auto_enroll` | `EnrollContactOnCreate` | `Contact::created` + company config |
| `auto_group` | `ApplyJourneyGroupRules` | Group attach matches a rule |
| `flow` | `AssignJourneyStage` node | Flowmaker execution |
| `action_agent` | `AgentToolRegistry::moveJourney` | AI tool `move_journey_stage` |
| `invoice_paid` | `InvoicePaidSyncService` | Invoice marked paid → Lead-to-Cash Paid |
| `cart_*` | `OutcomeJourneyEnroller` / commerce webhooks | Abandoned / recovered / lost |
| `booking_*` | `OutcomeJourneyEnroller` / `BookingNoShowService` | Booked / attended / no-show |
| `outcome_automation` | Playbook hooks | Installed suite automations |

### Enrollment besides drag-and-drop

**New contact.** `Contact::created` → `EnrollContactOnCreate` if `JOURNEYS_ENABLED`, `JOURNEYS_AUTO_ENROLL_NEW_CONTACTS`, and a numeric `JOURNEYS_DEFAULT_JOURNEY_ID`. Lands on first stage by `order`.

**Group rule.** Contacts store/update, Flowmaker `AssignGroup`, and some booking nodes call `GroupRuleBridge`. Unique rule per `(company, group, journey)`. Re-fires campaign even on same stage (`skipIfSameStage = false`).

### Contact lifecycle side effects

| Hook | Dispatcher | Payload |
|---|---|---|
| Contact created | `PublicWebhookDispatcher` | `contact.created` — id, name, phone, email |
| Name / email / phone changed | `PublicWebhookDispatcher` | `contact.updated` |
| Unsubscribe phrase | WhatsApp inbound + `ConsentService` | `subscribed = 0`, opt-out consent |
| Invoice paid | `InvoicePaidSyncService` | contact note, journey move, `invoice.paid`, outcome SKU |
| Platform event bus | HubSpot / Zapier / Sheets / QuickBooks | HubSpot: `POST crm/v3/objects/contacts` if email present |

Campaigns are the CRM sequence engine. Stage `campaign_id` must be a Wpbox **API** campaign (`Campaign::where('is_api', true)`). Inbox can confirm before send when `JOURNEYS_CONFIRM_BEFORE_SEND` is on. Blast campaigns only send to `subscribed = 1`.

### Analytics caveat

`JourneyAnalyticsService::conversionRates` is sequential stage headcount, not historical conversion. `RevenueDashboardService::pipelineValue()` counts **every** `journey_stage_contacts` row as an open deal, including terminal stages such as Won, Paid, Lost, then multiplies by average paid invoice amount.

---

## 4. Surfaces

Operators never leave chat if they do not have to. The primary CRM UI is the WhatsApp inbox plus sideapps. The contact list and kanban board are secondary. That is encoded in `hasSidebar` and Wpbox `sidebarData`.

### Human surfaces

| Surface | Route / view | CRM actions |
|---|---|---|
| Inbox | Wpbox chat | Assign agent, resolve, notes, groups, fields, journey move, bookings, catalog |
| Contact list | `contacts.index` | CRUD, bulk group, subscribe, merge, CSV export |
| Fields | `contacts.fields.index` | Define custom fields |
| Groups | `contacts.groups.index` | Segments used as tags |
| Import | `contacts.import.*` | Queued Excel, optional group attach |
| Journey list | `journies.index` | CRUD + template picker |
| Kanban | `journies.kanban` | Drag contacts, reorder stages, add contact |
| Group rules | Journies group-rules | Map group → stage |
| Analytics | `journies.analytics` | Counts, last moves, snapshot conversion % |
| Dashboard widget | `journies::reports.pipelines` | Open pipeline from `JourneyAnalyticsService` |

Inbox sideapps that are CRM: Contact (groups + fields), Notes, Contact journeys, Bookings, Catalog.

### APIs — three generations

| Generation | Auth | CRM coverage |
|---|---|---|
| `modules/Contacts/Routes/api.php` | `auth:api` stub | Returns the user. Not a contacts API. |
| Wpbox token API | Legacy company token | `getContacts`, `makeContact`, `updateContact`, `assignChat`, resolve |
| Journies external | Token on `web.php`, CSRF-exempt | `POST api/journies/external/move-contact`, `GET .../contact-status` |
| Public API v1 | `public.api` + idempotency | contacts CRUD-ish; conversations assign/resolve/notes; bookings; invoices |

There is **no** `/api/v1/journeys` resource.

### Automation ports that mutate CRM

| Port | Class | Writes |
|---|---|---|
| Assign agent | `Flowmaker\Models\Nodes\AssignAgent` | `user_id`, `enabled_ai_bot = false` |
| Assign group | `Nodes\AssignGroup` | `groups_contacts` + `GroupRuleBridge` |
| Assign stage | `Nodes\AssignJourneyStage` | `moveContactToStage` source=`flow` |
| WhatsApp Flow form | `Nodes\WhatsAppFlow` | custom fields, then group + stage |
| AI tool | `AgentToolRegistry` `move_journey_stage` | source=`action_agent` |
| Staff Agents module | `modules/Agents` | User role=`staff`; does not set `user_id` itself |

Assignment paths that *do* set `user_id`: inbox `ChatController::assignContact`, Wpbox `APIController::assignChat`, Flowmaker `AssignAgent`, public API `ConversationsController::assign`. When `agent_assigned_only` is on, staff only see chats they own or unassigned chats with last message from the contact.

### Playbook templates (the sales process)

| Template key | Stages | Installed by |
|---|---|---|
| `sales` | Lead → Qualified → Proposal → Won | Journey UI |
| `support` | New Ticket → In Progress → Waiting → Resolved | Journey UI |
| `marketing` | Subscriber → Engaged → Opportunity → Customer | Journey UI |
| `onboarding` | Welcome → Setup → Training → Active | Journey UI |
| `revenue` | New Lead → Qualified → Proposal → Paid → Onboarded | Journey UI |
| `ecommerce` | Inquiry → Quoted → Awaiting Payment → Paid → Fulfilled | Journey UI |
| `events` | Interested → Registered → Confirmed → Attended | Journey UI |
| `lead_to_cash` | New Lead → Qualified → Proposal → Awaiting Payment → Paid → Lost | `PlaybookInstaller` + sets default journey |
| `cart_recovery` | Abandoned → Nurturing → Checkout → Recovered → Lost | Outcome suite |
| `booking_convert` | Booked → Reminded → Attended → No-show → Rebooked | Outcome suite |

Playbook config: `config/outcome-playbooks.php`. `PlaybookInstaller` creates the journey from `JourneyTemplateService`, optionally installs a Flowmaker template, and stores journey/flow IDs on company config. For `lead_to_cash` it also sets `JOURNEYS_DEFAULT_JOURNEY_ID`.

---

## 5. Extracted design

Today CRM behavior is smeared across Contacts, Wpbox, Flowmaker, Journies, Invoice, Reminders, and `app/Services`. The extraction is **not** a new Deal object. It is a hard module boundary so messaging, flows, and commerce **call** CRM — CRM does not subclass messaging.

```
Adapters (WA · Flow · Invoice)
        ↓
Application use cases
        ├── Contact aggregate
        └── Pipeline aggregate (stages · activity)
                    ↓
            Projections (360 · inbox · API)
```

### Keep vs move vs stop subclassing

| Stay in CRM context | Stay outside (call in) | Stop doing |
|---|---|---|
| Contact identity, groups, fields, import, merge, consent | Message send/receive, bot reply, conversation workspace | Wpbox and Flowmaker extending `Contact` |
| Pipeline, stages, activity, group rules, templates | Campaign dispatch, Flowmaker nodes, AI tools | Declaring contact menus on Wpbox |
| Assignment as CRM ownership (`assignee_id`) | Inbox assigned-only filter as a messaging policy | Storing `has_chat` / `resolved_chat` on the person table long-term |
| Public CRM API: contacts, pipelines, activities | Invoice, reservation, cart as commerce events | Counting every stage membership as pipeline value |

### Proposed module shape

Keep Contacts + Journies behind a facade, or fold them into `modules/Crm`.

Application services already exist in pieces: `JourneyContactService`, `ContactMergeService`, `Customer360Service`, `OutcomeJourneyEnroller`. Extract those behind `CrmContactService` and `CrmPipelineService` with explicit DTOs. Flowmaker nodes, invoice paid, and booking no-show become adapters that call those services with a source enum — they already pass a source string.

Do **not** introduce a Deal table unless product wants amount / close-date / owner independent of the person. The current model is person-in-stage, which matches WhatsApp sales. If pipeline value must be real, add optional `value` / `currency` on the stage membership, or a thin Opportunity attached to `(contact, journey)`.

### Invariants to preserve

| Invariant | Today | Keep |
|---|---|---|
| One stage per journey per contact | Detach all stages then attach | Yes — encode in Pipeline aggregate |
| Company isolation | `CompanyScope` + session | Yes — prefer explicit `company_id` on every public method |
| Campaign on stage entry | Event → delayed job | Yes — via `CampaignPort`, not Wpbox model inside CRM |
| Opt-in for marketing | `contacts.subscribed` | Yes — Consent as first-class, `subscribed` as projection |
| Omnichannel identity | `channel_identities` | Yes — Contact has many identities; phone is optional |

### Phased extraction

| Phase | Change | Risk |
|---|---|---|
| 0 — Facade | Route all stage writes through `JourneyContactService` only; add Crm facade aliases | Low — already mostly true |
| 1 — API | Add public v1 journeys + activities; deprecate Journies external + Contacts stub | Low — additive |
| 2 — Composition | Stop subclassing Contact; Wpbox/Flowmaker use traits or services on the base model | Medium — wide import churn |
| 3 — Split state | Move `has_chat`, `resolved_chat`, `last_reply` onto `ConversationWorkspace` | High — inbox queries |

Do not rebuild a Salesforce clone. The architecture that already works is **person + conversation + named pipeline + commerce events**. Extraction should make that boundary explicit, give pipelines a real public API, and stop messaging from owning the person class.

---

## Quick file map

```
modules/Contacts/
  Models/{Contact,Group,Field,Country,ContactImport}.php
  Http/Controllers/{Main,GroupsController,FieldsController}.php
  Jobs/{ProcessContactImportJob,AssignContactImportGroupJob}.php
modules/Wpbox/Models/Contact.php
modules/Flowmaker/Models/Contact.php
modules/Flowmaker/Models/Nodes/{AssignAgent,AssignGroup,AssignJourneyStage,WhatsAppFlow}.php
modules/Journies/
  Models/{Journey,JourneyStage,JourneyActivity,JourneyGroupRule}.php
  Services/{JourneyContactService,JourneyAnalyticsService,JourneyTemplateService,JourneySettings}.php
  Events/{ContactMovedToStage,ContactAddedToGroup}.php
  Jobs/SendJourneyStageCampaign.php
app/Services/Contacts/ContactMergeService.php
app/Services/Platform/{Customer360Service,RevenueDashboardService,InvoicePaidSyncService}.php
app/Services/Outcomes/{PlaybookInstaller,OutcomeJourneyEnroller}.php
app/Services/Integrations/PlatformEventBus.php
app/Http/Controllers/Api/V1/ContactsController.php
app/Scopes/CompanyScope.php
config/outcome-playbooks.php
```
