<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Social\Livewire\PostComposer;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialMediaAsset;
use Modules\Social\Models\SocialOfferLink;
use Modules\Social\Models\SocialPost;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialPostComposerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_schedule_post_with_offer_and_media(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        $account = SocialAccount::factory()->forProvider('facebook')->create([
            'company_id' => $company->id,
        ]);
        $media = SocialMediaAsset::factory()->image()->create([
            'company_id' => $company->id,
        ]);

        $scheduledAt = now()->addDay()->format('Y-m-d\TH:i');

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id]);

        Livewire::test(PostComposer::class)
            ->set('content', 'Launch offer this weekend')
            ->set('selectedAccountIds', [$account->id])
            ->set('selectedMediaIds', [$media->id])
            ->set('scheduledAt', $scheduledAt)
            ->set('offerType', 'url')
            ->set('offerUrl', 'https://example.test/deal')
            ->call('schedule')
            ->assertHasNoErrors()
            ->assertRedirect(route('social.posts.index', ['status' => 'scheduled']));

        $post = SocialPost::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('status', 'scheduled')
            ->first();

        $this->assertNotNull($post);
        $this->assertSame('Launch offer this weekend', $post->defaultVersion->content);
        $this->assertSame([$media->id], $post->defaultVersion->media_ids);
        $this->assertTrue($post->accounts->contains('id', $account->id));

        $offer = SocialOfferLink::withoutGlobalScopes()
            ->where('social_post_id', $post->id)
            ->first();

        $this->assertNotNull($offer);
        $this->assertSame('url', $offer->offer_type);
        $this->assertSame('https://example.test/deal', $offer->url);
        $this->assertNotEmpty($offer->tracking_token);
    }
}
