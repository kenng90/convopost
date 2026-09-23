<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Social\Livewire\PostComposer;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPost;
use Modules\Social\Models\SocialQueueSlot;
use Modules\Social\Services\SocialQueueSlotService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialQueueSlotsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        config(['settings.forceUserToPay' => false]);
    }

    public function test_can_create_queue_slot_and_seed_recommended(): void
    {
        [$owner, $company] = $this->ownerCompany();

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->post(route('social.queue.store'), [
                'weekday' => 1,
                'time' => '09:00',
                'timezone' => 'UTC',
            ])
            ->assertRedirect(route('social.queue.index'));

        $this->assertSame(1, SocialQueueSlot::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->count());

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->post(route('social.queue.seed'))
            ->assertRedirect(route('social.queue.index'));

        $this->assertGreaterThan(1, SocialQueueSlot::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->count());
    }

    public function test_next_available_slot_skips_occupied_times(): void
    {
        [$owner, $company] = $this->ownerCompany();

        SocialQueueSlot::factory()->create([
            'company_id' => $company->id,
            'weekday' => 1,
            'time' => '09:00',
            'timezone' => config('app.timezone'),
        ]);
        SocialQueueSlot::factory()->create([
            'company_id' => $company->id,
            'weekday' => 1,
            'time' => '12:00',
            'timezone' => config('app.timezone'),
        ]);

        // Monday 2026-09-28 08:00 — next should be 09:00 same day.
        $this->travelTo(Carbon::parse('2026-09-28 08:00:00', config('app.timezone')));

        $service = app(SocialQueueSlotService::class);
        $first = $service->nextAvailableAt($company);

        $this->assertNotNull($first);
        $this->assertSame('2026-09-28 09:00:00', $first->format('Y-m-d H:i:s'));

        SocialPost::factory()->scheduled()->withDefaultVersion('Taken')->create([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'scheduled_at' => $first,
        ]);

        $second = $service->nextAvailableAt($company);
        $this->assertNotNull($second);
        $this->assertSame('2026-09-28 12:00:00', $second->format('Y-m-d H:i:s'));
    }

    public function test_add_to_queue_schedules_post_into_next_slot(): void
    {
        [$owner, $company] = $this->ownerCompany();

        SocialQueueSlot::factory()->create([
            'company_id' => $company->id,
            'weekday' => 1,
            'time' => '09:00',
            'timezone' => config('app.timezone'),
        ]);

        $this->travelTo(Carbon::parse('2026-09-28 08:00:00', config('app.timezone')));

        $account = SocialAccount::factory()->forProvider('facebook')->create([
            'company_id' => $company->id,
        ]);

        $this->actingAs($owner)->withSession(['company_id' => $company->id]);

        $component = Livewire::test(PostComposer::class)
            ->set('content', 'Queued post')
            ->set('selectedAccountIds', [$account->id])
            ->call('addToQueue')
            ->assertHasNoErrors()
            ->assertRedirect(route('social.posts.index', ['status' => 'scheduled']));

        $post = SocialPost::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('status', 'scheduled')
            ->first();

        $this->assertNotNull($post);
        $this->assertSame('2026-09-28 09:00:00', $post->scheduled_at->format('Y-m-d H:i:s'));
    }

    /**
     * @return array{0: User, 1: Company}
     */
    protected function ownerCompany(): array
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        return [$owner, $company];
    }
}
