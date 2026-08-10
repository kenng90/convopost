<?php

namespace Tests\Feature;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use App\Models\Messaging\ChannelIdentity;
use App\Models\User;
use App\Services\Campaign\CampaignAudienceResolver;
use App\Services\Campaign\CampaignEstimateService;
use App\Services\Contacts\ContactMergeService;
use App\Services\Messaging\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Contact;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactsOmnichannelAudienceTest extends TestCase
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
    }

    public function test_whatsapp_audience_excludes_instagram_only_contacts(): void
    {
        $wa = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'WA User',
            'phone' => '+15550001111',
            'subscribed' => 1,
        ]);

        $ig = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'IG User',
            'phone' => '',
            'subscribed' => 1,
        ]);

        ChannelIdentity::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'contact_id' => $ig->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_id' => 'ig-psid-1',
        ]);

        $audience = app(CampaignAudienceResolver::class)->resolve($this->company, [
            'channel' => Campaign::CHANNEL_WHATSAPP,
        ]);

        $this->assertSame(1, $audience['subscribed_count']);
        $this->assertTrue($audience['contacts']->contains('id', $wa->id));
        $this->assertFalse($audience['contacts']->contains('id', $ig->id));
    }

    public function test_estimate_uses_channel_deliverability(): void
    {
        Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'WA',
            'phone' => '+15550002222',
            'subscribed' => 1,
        ]);

        Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'IG',
            'phone' => '',
            'subscribed' => 1,
        ]);

        $estimate = app(CampaignEstimateService::class)->estimateForChannel(
            $this->company,
            Campaign::CHANNEL_WHATSAPP,
            [],
        );

        $this->assertSame(1, $estimate['subscribed_count']);
    }

    public function test_has_channel_segment_filter(): void
    {
        $ig = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'IG',
            'phone' => '',
            'subscribed' => 1,
        ]);

        ChannelIdentity::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'contact_id' => $ig->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_id' => 'ig-2',
        ]);

        Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'WA',
            'phone' => '+15550003333',
            'subscribed' => 1,
        ]);

        $query = Contact::query()->where('company_id', $this->company->id);
        app(CampaignAudienceResolver::class)->applySegmentFilters($query, [
            ['field' => 'has_channel', 'operator' => 'equals', 'value' => 'instagram'],
        ]);

        $this->assertSame([$ig->id], $query->pluck('id')->all());
    }

    public function test_ensure_for_contact_refuses_synthetic_instagram_ids(): void
    {
        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'No identity',
            'phone' => '',
            'subscribed' => 1,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(ConversationService::class)->ensureForContact(
            $contact,
            MessagingChannelType::Instagram,
        );
    }

    public function test_merge_moves_channel_identities(): void
    {
        $primary = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Primary',
            'phone' => '+15550004444',
            'subscribed' => 1,
        ]);

        $secondary = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Secondary',
            'phone' => '',
            'subscribed' => 1,
        ]);

        ChannelIdentity::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'contact_id' => $secondary->id,
            'channel' => MessagingChannelType::Messenger->value,
            'external_id' => 'ms-1',
        ]);

        app(ContactMergeService::class)->merge($primary, $secondary);

        $this->assertDatabaseHas('channel_identities', [
            'contact_id' => $primary->id,
            'channel' => MessagingChannelType::Messenger->value,
            'external_id' => 'ms-1',
        ]);

        $this->assertSoftDeleted('contacts', ['id' => $secondary->id]);
    }
}
