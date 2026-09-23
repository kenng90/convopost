# Offering mode: flip to `full` (post non-compete)

Use this runbook when counsel confirms the WhatsApp non-compete window has ended and product may market **inbox, campaigns, and messaging channels** again.

Until then, keep `OFFERING_MODE=social_commerce` (default). Social, Store, Pay, and outcome playbooks stay available; WhatsApp CRM UI stays dormant while code and webhooks remain loaded.

Related code: `config/offering.php`, `App\Support\Offering`, `App\Http\Middleware\BlockDormantWhatsappUi`.
Follow-up implementation tasks after this document: Phase **4.7–4.10** (re-enable surfaces, social→inbox wiring, landing/tests, regression).

---

## 1. Preconditions

- [ ] Legal sign-off that WhatsApp / messaging CRM surfaces may be offered.
- [ ] Staging and production backups / deploy window agreed.
- [ ] Confirm `.env` currently has `OFFERING_MODE=social_commerce` (or unset → defaults to social commerce).
- [ ] Read `config/offering.php` — dormant modules/routes lists are the source of truth for what unlocks when mode is `full`.

---

## 2. Environment flip

1. Set in `.env` (and any host / Forge / container secrets):

   ```env
   OFFERING_MODE=full
   ```

2. Optional: leave `SOCIAL_HOME_ROUTE` as-is (`social.calendar` by default). Owners opening `/dashboard` will no longer be forced to Social home once WhatsApp is enabled (`DashboardController` only redirects when `Offering::isSocialCommerce()`).

3. Clear config cache on each app server:

   ```bash
   php artisan config:clear
   php artisan config:cache   # if you normally cache config in production
   ```

4. Smoke-check in tinker / one-liner:

   ```bash
   php artisan tinker --execute="echo App\Support\Offering::mode().' '. (App\Support\Offering::whatsappEnabled() ? 'wa_on' : 'wa_off');"
   ```

   Expect: `full wa_on`.

Admin UI mirror: global field `OFFERING_MODE` in `config/config.php` (select: Social commerce vs Full platform). Prefer env for production so all nodes stay consistent.

---

## 3. Menus & route access checklist

When `Offering::isFull()`:

| Surface | Expected |
|---|---|
| Owner / staff module menus | WhatsApp-related aliases in `offering.whatsapp_dormant_modules` **reappear** (e.g. `whatsappcall`, `instagram`, `messenger`, …) |
| Named routes in `offering.whatsapp_dormant_routes` | **No longer** filtered or redirected (e.g. `chat.index`, `campaigns.index`, `whatsapp.setup`, templates, API UI) |
| `BlockDormantWhatsappUi` | No-ops when WhatsApp enabled; public `/webhook/wpbox/...` was never blocked |
| Org / manager module lists (`OrgAuthorization`, `User` menu helpers) | Same dormant-module rules — modules accessible again |
| Social calendar / insights | Still available; not removed by full mode |

Manual UI checks after deploy:

- [ ] Login as owner → see Chat / Campaigns / WhatsApp setup (or plan-gated equivalents).
- [ ] Open `route('chat.index')` → **not** redirected to Social calendar.
- [ ] Open Social calendar → still works (offering flip does not disable Social).

Automated coverage (already in repo):

```bash
php artisan test --filter=OfferingNavigationTest
php artisan test --filter=OfferingWhatsappUiBlockTest
php artisan test --filter=OfferingTest
```

---

## 4. Plans & entitlements

Plan tiers already include WhatsApp CRM capabilities for the flip (`config/plan-entitlements.php`):

| Tier | Inbox | Campaigns | Messaging channel plugins |
|---|---|---|---|
| Starter | yes | no | wpbox only |
| Growth | yes | yes | wpbox + commerce |
| Pro | yes | yes | flows, calls, IG/Messenger/TikTok, SMS/email, embed |
| Agency | all (`null` = unrestricted) | all | all |

After flipping mode:

- [ ] Re-run `php artisan db:seed --class=PlanEntitlementsSeeder` on staging/production if plan rows were customized, so plugins/capabilities match config.
- [ ] Spot-check `EnsurePlanPlugin` / `plan.capability:campaigns` on Chat / Campaigns for Growth and Pro tenants.
- [ ] Agency multi-company: switch company → Social calendar isolation still works; Chat uses the same company session.

```bash
php artisan test --filter=PlanEntitlements
php artisan test --filter=OfferingFullModeReenableTest
php artisan test --filter=SocialAgency
```

---

## 5. Landing & brand copy

`UnganishaBrand` and `modules/Wpsupportlanding` gate messaging sections on `Offering::whatsappEnabled()`.

When mode is `full`:

- [ ] Landing shows channels / WhatsApp messaging story (`id="channels"`, omnichannel headline).
- [ ] When still `social_commerce`, landing must **not** lead with dormant WhatsApp CRM claims (existing `LandingPageTest` / `UnganishaBrandTest`).

```bash
php artisan test --filter=LandingPageTest
php artisan test --filter=UnganishaBrandTest
```

Phase **4.9** covers any further marketing copy polish after the flip.

---

## 6. Regression test pack (run after flip on staging)

Minimum offering pack:

```bash
php artisan test --filter='OfferingTest|OfferingNavigationTest|OfferingWhatsappUiBlockTest|LandingPageTest|UnganishaBrandTest'
```

Broader Social + commerce smoke (recommended before production):

```bash
php artisan test --filter='Social|Offering|LandingPage|OutcomeSku|SocialOutcome|SocialInsights|SocialOnboarding'
```

Format dirty PHP before merge:

```bash
php vendor/bin/pint --dirty
```

Phase **4.10** is the final all-inclusive regression after 4.7–4.9 land.

---

## 7. What this flip does *not* do

Setting `OFFERING_MODE=full` alone:

- Does **not** connect Meta WhatsApp Business / Embedded Signup for tenants — they still complete WhatsApp setup.
- Does **not** wire Social comments/DMs into Inbox or Flowmaker (that is Phase **4.8**).
- Does **not** remove Social commerce; it only unlocks messaging UI that was gated.
- Does **not** change webhook URLs; Wpbox receive paths stay live in both modes.

---

## 8. Rollback

1. Set `OFFERING_MODE=social_commerce`.
2. `php artisan config:clear` (and re-cache if used).
3. Re-run:

   ```bash
   php artisan test --filter='OfferingWhatsappUiBlockTest|OfferingNavigationTest|LandingPageTest'
   ```

   Expect Chat/Campaigns redirects to Social home and landing without channels hero.

---

## 9. Handoff to Phase 4.7+

| Phase | Intent |
|---|---|
| **4.7** | Product/code commit: any remaining menu, plan, or copy gaps once legal OK |
| **4.8** | Social comment/DM → Inbox → optional Flow trigger |
| **4.9** | Full omnichannel landing/tests |
| **4.10** | Final regression + Pint |

Keep this runbook updated if `whatsapp_dormant_modules` or `whatsapp_dormant_routes` change.
