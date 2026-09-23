<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Social\Livewire\ContentCalendar;
use Modules\Social\Models\SocialPost;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialContentCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_reschedule_post_from_calendar(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        $original = now()->addDay()->setTime(10, 0);
        $post = SocialPost::factory()->scheduled()->withDefaultVersion('Calendar post')->create([
            'company_id' => $company->id,
            'scheduled_at' => $original,
        ]);

        $newTime = now()->addDays(3)->setTime(15, 30);

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id]);

        Livewire::test(ContentCalendar::class)
            ->set('cursorDate', $original->toDateString())
            ->call('startReschedule', $post->id)
            ->assertSet('reschedulingPostId', $post->id)
            ->set('rescheduleAt', $newTime->format('Y-m-d\TH:i'))
            ->call('saveReschedule')
            ->assertHasNoErrors()
            ->assertSet('reschedulingPostId', null);

        $post->refresh();

        $this->assertSame('scheduled', $post->status);
        $this->assertTrue($post->scheduled_at->equalTo($newTime->copy()->second(0)));
    }

    public function test_calendar_page_loads_for_owner(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1, 'name' => 'Acme Social Co']);
        $owner->update(['company_id' => $company->id]);

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->get(route('social.calendar'))
            ->assertOk()
            ->assertSeeLivewire(ContentCalendar::class)
            ->assertSee('Acme Social Co')
            ->assertSee(__('Active Social workspace'));
    }

    public function test_calendar_only_shows_posts_for_active_company_workspace(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $companyA = Company::factory()->create(['user_id' => $owner->id, 'active' => 1, 'name' => 'Client A']);
        $companyB = Company::factory()->create(['user_id' => $owner->id, 'active' => 1, 'name' => 'Client B']);
        $owner->update(['company_id' => $companyA->id]);

        $when = now()->addDay()->setTime(11, 0);

        SocialPost::factory()->scheduled()->withDefaultVersion('Only on Client A')->create([
            'company_id' => $companyA->id,
            'scheduled_at' => $when,
        ]);
        SocialPost::factory()->scheduled()->withDefaultVersion('Only on Client B')->create([
            'company_id' => $companyB->id,
            'scheduled_at' => $when,
        ]);

        $this->actingAs($owner)->withSession(['company_id' => $companyA->id]);

        Livewire::test(ContentCalendar::class)
            ->set('cursorDate', $when->toDateString())
            ->assertSee('Only on Client A')
            ->assertDontSee('Only on Client B')
            ->assertSee('Client A');
    }

    public function test_switching_company_from_social_lands_on_isolated_calendar(): void
    {
        Role::firstOrCreate(['name' => 'owner']);
        config(['settings.forceUserToPay' => false]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $companyA = Company::factory()->create(['user_id' => $owner->id, 'active' => 1, 'name' => 'Workspace A']);
        $companyB = Company::factory()->create(['user_id' => $owner->id, 'active' => 1, 'name' => 'Workspace B']);
        $owner->update(['company_id' => $companyA->id]);

        $when = now()->addDays(2)->setTime(9, 0);
        SocialPost::factory()->scheduled()->withDefaultVersion('B calendar post')->create([
            'company_id' => $companyB->id,
            'scheduled_at' => $when,
        ]);

        $this->actingAs($owner)
            ->withSession(['company_id' => $companyA->id])
            ->get(route('admin.companies.switch', ['company' => $companyB->id, 'to' => 'social.calendar']))
            ->assertRedirect(route('social.calendar'));

        $this->assertSame($companyB->id, session('company_id'));
        $this->assertSame($companyB->id, $owner->fresh()->company_id);

        $this->actingAs($owner)
            ->withSession(['company_id' => $companyB->id])
            ->get(route('social.calendar'))
            ->assertOk()
            ->assertSee('Workspace B')
            ->assertSee('B calendar post');
    }
}
