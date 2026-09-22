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
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->get(route('social.calendar'))
            ->assertOk()
            ->assertSeeLivewire(ContentCalendar::class);
    }
}
