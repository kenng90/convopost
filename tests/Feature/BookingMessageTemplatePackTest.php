<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Reminders\Services\BookingMessageContextService;
use Modules\Reminders\Services\BookingMessageTemplatePackService;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Template;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingMessageTemplatePackTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');
        $this->company = Company::factory()->create(['user_id' => $this->owner->id]);
        $this->owner->update(['company_id' => $this->company->id]);
        session(['company_id' => $this->company->id]);

        $this->company->setConfig('whatsapp_webhook_verified', 'yes');
        $this->company->setConfig('whatsapp_settings_done', 'yes');
        $this->company->setConfig('whatsapp_permanent_access_token', 'test-token');
        $this->company->setConfig('whatsapp_business_account_id', 'waba-123');
        $this->company->setConfig('whatsapp_phone_number_id', 'phone-123');
    }

    private int $metaTemplateCounter = 900000;

    private function fakeMetaTemplateApi(?callable $postHandler = null): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => function ($request) use ($postHandler) {
                if ($request->method() === 'GET') {
                    return Http::response(['data' => []], 200);
                }

                if ($request->method() === 'DELETE') {
                    return Http::response(['success' => true], 200);
                }

                if ($postHandler !== null) {
                    $response = $postHandler($request);

                    if ($response !== null) {
                        return $response;
                    }
                }

                $this->metaTemplateCounter++;

                return Http::response([
                    'id' => (string) $this->metaTemplateCounter,
                    'status' => 'PENDING',
                    'category' => 'UTILITY',
                ], 200);
            },
        ]);
    }

    public function test_confirmation_templates_include_booking_reference_mapping(): void
    {
        $definitions = config('booking-message-templates');

        $appointment = $definitions['appointment_booking_confirmation'];
        $this->assertStringContainsString('Reference: {{2}}', $appointment['body']);
        $this->assertSame(
            (string) BookingMessageContextService::FIELD_EXTERNAL_ID,
            $appointment['variables_match']['body']['2']
        );

        $event = $definitions['event_booking_confirmation'];
        $this->assertStringContainsString('Reference: {{3}}', $event['body']);
        $this->assertSame(
            (string) BookingMessageContextService::FIELD_EXTERNAL_ID,
            $event['variables_match']['body']['3']
        );
    }

    public function test_install_creates_six_templates_and_reminder_campaigns(): void
    {
        $this->fakeMetaTemplateApi();

        $result = app(BookingMessageTemplatePackService::class)->installForCompany($this->company);

        $this->assertTrue($result['success']);
        $this->assertCount(6, $result['results']);

        $definitions = config('booking-message-templates');
        $this->assertCount(6, $definitions);

        foreach ($definitions as $key => $definition) {
            $template = Template::withoutGlobalScopes()
                ->where('company_id', $this->company->id)
                ->where('name', $definition['template_name'])
                ->first();

            $this->assertNotNull($template, "Template missing for {$key}");

            $campaign = Campaign::withoutGlobalScopes()
                ->where('company_id', $this->company->id)
                ->where('template_id', $template->id)
                ->where('is_reminder', true)
                ->first();

            $this->assertNotNull($campaign, "Campaign missing for {$key}");
            $this->assertSame($definition['campaign_name'], $campaign->name);

            $match = json_decode($campaign->variables_match, true);
            $this->assertSame($definition['variables_match'], $match);
        }

        $this->assertSame('yes', $this->company->fresh()->getConfig(BookingMessageTemplatePackService::CONFIG_INSTALLED_KEY));
    }

    public function test_install_is_idempotent(): void
    {
        $this->fakeMetaTemplateApi();

        $service = app(BookingMessageTemplatePackService::class);
        $service->installForCompany($this->company);
        $service->installForCompany($this->company);

        $this->assertSame(6, Template::withoutGlobalScopes()->where('company_id', $this->company->id)->count());
        $this->assertSame(
            6,
            Campaign::withoutGlobalScopes()
                ->where('company_id', $this->company->id)
                ->where('is_reminder', true)
                ->count()
        );
    }

    public function test_event_confirmation_campaign_maps_booking_fields(): void
    {
        $this->fakeMetaTemplateApi();
        app(BookingMessageTemplatePackService::class)->installForCompany($this->company);

        $campaign = Campaign::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('name', 'Event booking confirmation')
            ->first();

        $match = json_decode($campaign->variables_match, true);

        $this->assertSame('-1', $match['body']['1']);
        $this->assertSame((string) BookingMessageContextService::FIELD_EVENT_TITLE, $match['body']['2']);
        $this->assertSame((string) BookingMessageContextService::FIELD_EXTERNAL_ID, $match['body']['3']);
        $this->assertSame((string) BookingMessageContextService::FIELD_START_DATE, $match['body']['4']);
        $this->assertSame((string) BookingMessageContextService::FIELD_START_TIME, $match['body']['5']);
        $this->assertSame((string) BookingMessageContextService::FIELD_LOCATION, $match['body']['6']);
    }

    public function test_install_requires_whatsapp_credentials(): void
    {
        $company = Company::factory()->create();
        $company->setConfig('whatsapp_business_account_id', '');

        $result = app(BookingMessageTemplatePackService::class)->installForCompany($company);

        $this->assertFalse($result['success']);
        $this->assertSame('missing_credentials', $result['status']);
    }

    public function test_overview_shows_install_prompt_when_whatsapp_ready(): void
    {
        $response = $this->actingAs($this->owner)
            ->withSession(['company_id' => $this->company->id])
            ->get(route('reminders.overview.index'));

        $response->assertOk();
        $response->assertSee('Install booking message pack');
    }

    public function test_install_route_creates_pack_and_redirects(): void
    {
        $this->fakeMetaTemplateApi();

        $response = $this->actingAs($this->owner)
            ->withSession(['company_id' => $this->company->id])
            ->post(route('reminders.booking-templates.install'));

        $response->assertRedirect(route('reminders.overview.index'));
        $response->assertSessionHas('status');

        $this->assertSame(6, Campaign::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('is_reminder', true)
            ->count());
    }

    public function test_overview_shows_repair_prompt_after_partial_delete(): void
    {
        $this->fakeMetaTemplateApi();
        app(BookingMessageTemplatePackService::class)->installForCompany($this->company);

        Template::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('name', config('booking-message-templates.event_booking_confirmation.template_name'))
            ->delete();

        $response = $this->actingAs($this->owner)
            ->withSession(['company_id' => $this->company->id])
            ->get(route('reminders.overview.index'));

        $response->assertOk();
        $response->assertSee('Repair booking message pack');
        $response->assertDontSee('Install booking message pack');
    }

    public function test_overview_shows_repair_prompt_after_full_delete(): void
    {
        $this->fakeMetaTemplateApi();
        app(BookingMessageTemplatePackService::class)->installForCompany($this->company);

        Template::withoutGlobalScopes()->where('company_id', $this->company->id)->delete();
        Campaign::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('is_reminder', true)
            ->delete();

        $response = $this->actingAs($this->owner)
            ->withSession(['company_id' => $this->company->id])
            ->get(route('reminders.overview.index'));

        $response->assertOk();
        $response->assertSee('Repair booking message pack');
    }

    public function test_repair_recreates_missing_pack_items(): void
    {
        $this->fakeMetaTemplateApi();
        $service = app(BookingMessageTemplatePackService::class);
        $service->installForCompany($this->company);

        Template::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->whereIn('name', [
                config('booking-message-templates.event_booking_confirmation.template_name'),
                'appointment_reminder',
            ])
            ->delete();

        $result = $service->installForCompany($this->company->fresh());

        $this->assertTrue($result['success']);
        $this->assertSame('repaired', $result['status']);
        $this->assertSame(
            6,
            Template::withoutGlobalScopes()
                ->where('company_id', $this->company->id)
                ->whereNull('deleted_at')
                ->count()
        );
    }

    public function test_repair_recreates_soft_deleted_referenced_template(): void
    {
        $this->fakeMetaTemplateApi();
        $service = app(BookingMessageTemplatePackService::class);
        $service->installForCompany($this->company);

        $template = Template::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('name', config('booking-message-templates.event_booking_confirmation.template_name'))
            ->first();

        $this->assertNotNull($template);
        $template->delete();

        $status = $service->statusForCompany($this->company->fresh());
        $this->assertTrue($status['show_repair_prompt']);

        $result = $service->installForCompany($this->company->fresh());

        $this->assertTrue($result['success']);
        $this->assertNotNull(
            Template::withoutGlobalScopes()
                ->where('company_id', $this->company->id)
                ->where('name', config('booking-message-templates.event_booking_confirmation.template_name'))
                ->first()
        );
    }

    public function test_status_marks_installed_only_when_all_six_are_present(): void
    {
        $this->fakeMetaTemplateApi();
        $service = app(BookingMessageTemplatePackService::class);
        $service->installForCompany($this->company);

        Template::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('name', 'event_thank_you')
            ->delete();

        $status = $service->statusForCompany($this->company->fresh());

        $this->assertFalse($status['installed']);
        $this->assertTrue($status['show_repair_prompt']);
        $this->assertFalse($status['show_install_prompt']);
        $this->assertSame(5, $status['present_count']);
        $this->assertSame(6, $status['total_count']);
    }

    public function test_repair_retries_with_alternate_name_when_meta_rejects_base_name(): void
    {
        $this->fakeMetaTemplateApi(function ($request) {
            $payload = $request->data();
            $name = $payload['name'] ?? '';

            if ($name === 'event_thank_you') {
                return Http::response([
                    'error' => [
                        'message' => 'Invalid parameter',
                        'error_user_title' => 'Template name unavailable',
                        'error_user_msg' => 'A template with this name was recently deleted.',
                    ],
                ], 400);
            }

            return null;
        });

        $service = app(BookingMessageTemplatePackService::class);
        $service->installForCompany($this->company);

        Template::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('name', 'event_thank_you')
            ->delete();

        Campaign::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('name', 'Event thank you')
            ->delete();

        $result = $service->installForCompany($this->company->fresh());

        $this->assertTrue($result['success']);
        $this->assertSame('repaired', $result['status']);

        $template = Template::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('name', 'event_thank_you_v2')
            ->first();

        $this->assertNotNull($template);
        $this->assertSame(
            'event_thank_you_v2',
            $this->company->fresh()->getConfig('booking_template_name_event_thank_you')
        );

        $status = $service->statusForCompany($this->company->fresh());
        $this->assertTrue($status['installed']);
        $this->assertSame(6, $status['present_count']);
    }
}
