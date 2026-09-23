<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Social\Livewire\PostComposer;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialLabel;
use Modules\Social\Models\SocialPost;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialLabelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        config(['settings.forceUserToPay' => false]);
    }

    public function test_can_create_label_attach_to_post_and_filter(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->post(route('social.labels.store'), [
                'name' => 'Promo',
                'color' => '#112233',
            ])
            ->assertRedirect(route('social.labels.index'));

        $label = SocialLabel::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Promo')
            ->first();

        $this->assertNotNull($label);

        $account = SocialAccount::factory()->forProvider('facebook')->create([
            'company_id' => $company->id,
        ]);

        Livewire::test(PostComposer::class)
            ->set('content', 'Labeled promo post')
            ->set('selectedAccountIds', [$account->id])
            ->set('selectedLabelIds', [$label->id])
            ->call('saveDraft')
            ->assertHasNoErrors();

        $post = SocialPost::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('status', 'draft')
            ->first();

        $this->assertNotNull($post);
        $this->assertSame([$label->id], $post->label_ids);

        $other = SocialPost::factory()->withDefaultVersion('No label')->create([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'label_ids' => [],
        ]);

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->get(route('social.posts.index', ['label' => $label->id]))
            ->assertOk()
            ->assertSee('Labeled promo post')
            ->assertDontSee('No label');

        $this->assertTrue(
            SocialPost::withoutGlobalScopes()
                ->whereKey($other->id)
                ->whereJsonContains('label_ids', $label->id)
                ->doesntExist()
        );
    }
}
