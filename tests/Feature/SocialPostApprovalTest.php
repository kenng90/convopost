<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use Database\Seeders\PlanEntitlementsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Social\Models\SocialPost;
use Modules\Social\Models\SocialPostActivity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialPostApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        Role::firstOrCreate(['name' => 'staff']);
        $this->seed(PlanEntitlementsSeeder::class);

        config([
            'settings.forceUserToPay' => false,
        ]);
    }

    public function test_staff_can_submit_but_cannot_approve(): void
    {
        [$owner, $staff, $company, $post] = $this->teamWithDraftPost();

        $this->actingAs($staff)
            ->withSession(['company_id' => $company->id])
            ->post(route('social.posts.submit-approval', $post))
            ->assertRedirect(route('social.posts.show', $post));

        $post->refresh();
        $this->assertSame('pending', $post->approval_status);
        $this->assertNotNull($post->submitted_at);

        $this->assertDatabaseHas('social_post_activities', [
            'social_post_id' => $post->id,
            'action' => 'submitted',
            'user_id' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->withSession(['company_id' => $company->id])
            ->post(route('social.posts.approve', $post))
            ->assertSessionHasErrors('approval');

        $post->refresh();
        $this->assertSame('pending', $post->approval_status);
    }

    public function test_owner_can_approve_and_reject(): void
    {
        [$owner, $staff, $company, $post] = $this->teamWithDraftPost();

        $this->actingAs($staff)
            ->withSession(['company_id' => $company->id])
            ->post(route('social.posts.submit-approval', $post))
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->post(route('social.posts.approve', $post))
            ->assertRedirect(route('social.posts.show', $post));

        $post->refresh();
        $this->assertSame('approved', $post->approval_status);
        $this->assertSame($owner->id, $post->reviewed_by);
        $this->assertNotNull($post->reviewed_at);

        $this->assertTrue(
            SocialPostActivity::withoutGlobalScopes()
                ->where('social_post_id', $post->id)
                ->where('action', 'approved')
                ->where('user_id', $owner->id)
                ->exists()
        );

        $rejected = SocialPost::factory()->withDefaultVersion('Needs edits')->create([
            'company_id' => $company->id,
            'user_id' => $staff->id,
            'status' => 'draft',
            'approval_status' => 'pending',
            'submitted_at' => now(),
        ]);

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->post(route('social.posts.reject', $rejected), [
                'rejection_reason' => 'Please fix the CTA.',
            ])
            ->assertRedirect(route('social.posts.show', $rejected));

        $rejected->refresh();
        $this->assertSame('rejected', $rejected->approval_status);
        $this->assertSame('Please fix the CTA.', $rejected->rejection_reason);
        $this->assertSame('draft', $rejected->status);
    }

    public function test_post_show_includes_activity_timeline(): void
    {
        [$owner, $staff, $company, $post] = $this->teamWithDraftPost();

        $this->actingAs($staff)
            ->withSession(['company_id' => $company->id])
            ->post(route('social.posts.submit-approval', $post));

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->get(route('social.posts.show', $post))
            ->assertOk()
            ->assertSee(__('Activity'))
            ->assertSee(__('Submitted'));
    }

    /**
     * @return array{0: User, 1: User, 2: Company, 3: SocialPost}
     */
    private function teamWithDraftPost(): array
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create([
            'user_id' => $owner->id,
            'active' => 1,
        ]);
        $owner->update(['company_id' => $company->id]);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $staff->assignRole('staff');

        CompanyMembership::query()->create([
            'user_id' => $staff->id,
            'company_id' => $company->id,
            'role' => CompanyMembership::ROLE_AGENT,
            'status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $post = SocialPost::factory()->withDefaultVersion('Team draft for review')->create([
            'company_id' => $company->id,
            'user_id' => $staff->id,
            'status' => 'draft',
            'approval_status' => 'none',
        ]);

        return [$owner, $staff, $company, $post];
    }
}
