<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\Outcomes\OutcomeSkuBiller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Wpbox\Models\Contact;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OutcomeSkuBillingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');
        $this->company = Company::factory()->create(['user_id' => $this->owner->id]);
        $this->owner->update(['company_id' => $this->company->id]);
        session(['company_id' => $this->company->id]);
        config(['settings.enable_credits' => true]);
        $this->owner->addCredits(100, 'test');

        $this->contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Buyer',
            'phone' => '+254700000111',
        ]);
    }

    public function test_recovered_cart_bills_outcome_sku_once(): void
    {
        $biller = app(OutcomeSkuBiller::class);

        $first = $biller->record($this->company, $this->contact, 'cart_recovery', 'cart.recovered', 1500, 'contact', (string) $this->contact->id);
        $second = $biller->record($this->company, $this->contact, 'cart_recovery', 'cart.recovered', 1500, 'contact', (string) $this->contact->id);

        $this->assertTrue($first['billed']);
        $this->assertSame(10, $first['credits']);
        $this->assertFalse($second['billed']);
        $this->assertSame('duplicate', $second['skipped']);
        $this->assertDatabaseHas('outcome_attributions', [
            'company_id' => $this->company->id,
            'sku' => 'cart_recovery',
            'credits_charged' => 10,
        ]);
        $this->assertEquals(90, (int) $this->owner->fresh()->getTotalRemainingCredits());
    }

    public function test_no_show_voids_booking_sku_inside_guarantee_window(): void
    {
        $biller = app(OutcomeSkuBiller::class);
        $biller->record($this->company, $this->contact, 'booking_convert', 'booking.attended', 0, 'contact', (string) $this->contact->id);

        $void = $biller->void($this->company, 'booking_convert', 'contact', (string) $this->contact->id, $this->contact);

        $this->assertTrue($void['voided']);
        $this->assertSame(8, $void['credits_refunded']);
        $this->assertEquals(100, (int) $this->owner->fresh()->getTotalRemainingCredits());
    }
}
