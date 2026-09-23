<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Invoice\Models\Invoice;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPost;
use Modules\Social\Services\SocialOnboardingService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialOnboardingChecklistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        config(['settings.forceUserToPay' => false]);
    }

    public function test_checklist_tracks_connect_publish_and_attributed_order(): void
    {
        $company = Company::factory()->create();

        $service = app(SocialOnboardingService::class);
        $empty = $service->forCompany($company);

        $this->assertFalse($empty['complete']);
        $this->assertSame(0, $empty['completed_count']);
        $this->assertFalse($empty['steps'][0]['done']);
        $this->assertFalse($empty['steps'][1]['done']);
        $this->assertFalse($empty['steps'][2]['done']);

        SocialAccount::factory()->forProvider('facebook')->create([
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $afterAccount = $service->forCompany($company);
        $this->assertTrue($afterAccount['steps'][0]['done']);
        $this->assertFalse($afterAccount['steps'][1]['done']);
        $this->assertSame(1, $afterAccount['completed_count']);

        $post = SocialPost::factory()->published()->withDefaultVersion('Live launch')->create([
            'company_id' => $company->id,
        ]);

        $afterPost = $service->forCompany($company);
        $this->assertTrue($afterPost['steps'][1]['done']);
        $this->assertFalse($afterPost['steps'][2]['done']);
        $this->assertSame(2, $afterPost['completed_count']);

        Invoice::create([
            'company_id' => $company->id,
            'social_post_id' => $post->id,
            'invoice_number' => 'INV-ONB-1',
            'customer_name' => 'Buyer',
            'customer_phone' => '254700000111',
            'amount' => 1500,
            'currency' => 'KES',
            'status' => 'paid',
        ]);

        $done = $service->forCompany($company);
        $this->assertTrue($done['complete']);
        $this->assertSame(3, $done['completed_count']);
        $this->assertTrue($done['steps'][2]['done']);
    }

    public function test_calendar_shows_checklist_until_complete(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->get(route('social.calendar'))
            ->assertOk()
            ->assertSee(__('Social commerce checklist'))
            ->assertSee(__('Connect a social account'))
            ->assertSee(__('Publish your first post'))
            ->assertSee(__('Get your first attributed order'));

        SocialAccount::factory()->forProvider('facebook')->create([
            'company_id' => $company->id,
            'status' => 'active',
        ]);
        $post = SocialPost::factory()->published()->withDefaultVersion('Done post')->create([
            'company_id' => $company->id,
        ]);
        Invoice::create([
            'company_id' => $company->id,
            'social_post_id' => $post->id,
            'invoice_number' => 'INV-ONB-2',
            'customer_name' => 'Buyer',
            'customer_phone' => '254700000222',
            'amount' => 900,
            'currency' => 'KES',
            'status' => 'paid',
        ]);

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->get(route('social.calendar'))
            ->assertOk()
            ->assertDontSee(__('Social commerce checklist'));
    }
}
