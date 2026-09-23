<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Social\Livewire\PostComposer;
use Modules\Social\Models\SocialTemplate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialTemplatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        config(['settings.forceUserToPay' => false]);
    }

    public function test_owner_can_create_update_and_delete_template(): void
    {
        [$owner, $company] = $this->ownerWithCompany();

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->post(route('social.templates.store'), [
                'name' => 'Weekend promo',
                'content' => 'Shop the weekend deal!',
                'category' => 'promo',
                'is_active' => '1',
            ])
            ->assertRedirect(route('social.templates.index'));

        $template = SocialTemplate::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Weekend promo')
            ->first();

        $this->assertNotNull($template);
        $this->assertTrue($template->is_active);

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->put(route('social.templates.update', $template), [
                'name' => 'Weekend promo v2',
                'content' => 'Updated caption',
                'category' => 'launch',
                'is_active' => '0',
            ])
            ->assertRedirect(route('social.templates.index'));

        $template->refresh();
        $this->assertSame('Weekend promo v2', $template->name);
        $this->assertFalse($template->is_active);

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->delete(route('social.templates.destroy', $template))
            ->assertRedirect(route('social.templates.index'));

        $this->assertSoftDeleted('social_templates', ['id' => $template->id]);
    }

    public function test_composer_can_apply_active_template(): void
    {
        [$owner, $company] = $this->ownerWithCompany();

        $template = SocialTemplate::factory()->create([
            'company_id' => $company->id,
            'name' => 'Apply me',
            'content' => 'Template body for composer',
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id]);

        Livewire::test(PostComposer::class)
            ->call('applyTemplate', $template->id)
            ->assertSet('content', 'Template body for composer');
    }

    /**
     * @return array{0: User, 1: Company}
     */
    private function ownerWithCompany(): array
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        return [$owner, $company];
    }
}
