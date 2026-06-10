<?php

namespace Tests\Unit;

use Mockery;
use Modules\Whatsappcall\Services\CallBriefService;
use Modules\Wpbox\Models\Contact;
use Tests\TestCase;

class CallBriefServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_apply_confirmed_fields_preserves_existing_contact_name(): void
    {
        $contact = Mockery::mock(Contact::class)->makePartial();
        $contact->name = 'Alice Smith';
        $contact->company_id = 1;
        $contact->shouldNotReceive('save');

        $service = new CallBriefService;
        $service->applyConfirmedFields($contact, [
            ['key' => 'name', 'value' => 'Voice Caller', 'status' => 'confirmed'],
        ]);

        $this->assertSame('Alice Smith', $contact->name);
    }

    public function test_apply_confirmed_fields_allows_corrected_name(): void
    {
        $contact = Mockery::mock(Contact::class)->makePartial();
        $contact->name = 'Alice Smith';
        $contact->company_id = 1;
        $contact->shouldReceive('save')->once()->andReturnTrue();

        $service = new CallBriefService;
        $service->applyConfirmedFields($contact, [
            ['key' => 'name', 'value' => 'Alicia Smith', 'status' => 'corrected'],
        ]);

        $this->assertSame('Alicia Smith', $contact->name);
    }
}
