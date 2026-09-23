<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Social\Livewire\PostComposer;
use Modules\Social\Models\SocialHashtagGroup;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialHashtagGroupsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        config(['settings.forceUserToPay' => false]);
    }

    public function test_owner_can_create_hashtag_group_and_insert_into_composer(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->post(route('social.hashtags.store'), [
                'name' => 'Commerce',
                'tags' => '#kenya mpesa, shop',
            ])
            ->assertRedirect(route('social.hashtags.index'));

        $group = SocialHashtagGroup::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Commerce')
            ->first();

        $this->assertNotNull($group);
        $this->assertSame(['kenya', 'mpesa', 'shop'], $group->tags);

        Livewire::test(PostComposer::class)
            ->set('content', 'Hello')
            ->call('insertHashtagGroup', $group->id)
            ->assertSet('content', "Hello\n\n#kenya #mpesa #shop");
    }
}
