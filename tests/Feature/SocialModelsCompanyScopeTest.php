<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialOfferLink;
use Modules\Social\Models\SocialPost;
use Modules\Social\Models\SocialPostAccount;
use Tests\TestCase;

class SocialModelsCompanyScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_post_with_version_and_account_under_company_scope(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        session(['company_id' => $companyA->id]);

        $account = SocialAccount::factory()->forProvider('facebook')->create([
            'company_id' => $companyA->id,
        ]);

        $post = SocialPost::factory()->scheduled()->withDefaultVersion('Hello social')->create([
            'company_id' => $companyA->id,
        ]);

        SocialPostAccount::factory()->create([
            'social_post_id' => $post->id,
            'social_account_id' => $account->id,
            'status' => 'pending',
        ]);

        SocialOfferLink::factory()->create([
            'company_id' => $companyA->id,
            'social_post_id' => $post->id,
            'url' => 'https://example.test/offer',
        ]);

        $this->assertSame(1, SocialPost::query()->count());
        $this->assertSame(1, SocialAccount::query()->count());
        $this->assertTrue($post->versions()->where('provider', 'default')->exists());
        $this->assertSame(1, $post->accounts()->count());
        $this->assertNotNull($post->offerLink);
        $this->assertSame('Hello social', $post->defaultVersion->content);

        session(['company_id' => $companyB->id]);

        $this->assertSame(0, SocialPost::query()->count());
        $this->assertSame(0, SocialAccount::query()->count());
        $this->assertSame(0, SocialOfferLink::query()->count());

        $this->assertTrue(
            SocialPost::withoutGlobalScopes()->whereKey($post->id)->exists()
        );
    }
}
