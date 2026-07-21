<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\WhatsappFlow;
use App\Services\Flowmaker\FlowTemplateService;
use App\Services\WhatsappFormTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappFormTemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_from_template_uniquifies_duplicate_names(): void
    {
        $company = Company::factory()->create();
        $service = app(WhatsappFormTemplateService::class);

        $first = $service->createFromTemplate('healthcare_appointment', (int) $company->id);
        $second = $service->createFromTemplate('healthcare_appointment', (int) $company->id);

        $this->assertSame('Healthcare Appointment', $first->name);
        $this->assertSame('Healthcare Appointment (2)', $second->name);
        $this->assertSame(1, $first->version);
        $this->assertSame(1, $second->version);
    }

    public function test_find_or_create_reuses_existing_bundle_form(): void
    {
        $company = Company::factory()->create();
        $service = app(WhatsappFormTemplateService::class);

        $first = $service->findOrCreateFromTemplate('healthcare_appointment', (int) $company->id);
        $second = $service->findOrCreateFromTemplate('healthcare_appointment', (int) $company->id);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, WhatsappFlow::query()->where('company_id', $company->id)->count());
    }

    public function test_healthcare_bot_can_be_installed_twice_without_duplicate_form_collision(): void
    {
        $company = Company::factory()->create();
        session(['company_id' => $company->id]);

        $service = app(FlowTemplateService::class);
        $firstFlow = $service->install('healthcare_clinic_bot');
        $secondFlow = $service->install('healthcare_clinic_bot', 'Healthcare Clinic Bot (copy)');

        $this->assertNotNull($firstFlow);
        $this->assertNotNull($secondFlow);
        $this->assertNotSame($firstFlow->id, $secondFlow->id);

        $this->assertSame(1, WhatsappFlow::query()
            ->where('company_id', $company->id)
            ->where('form_bundle_key', 'healthcare_appointment')
            ->count());

        $firstData = json_decode($firstFlow->flow_data, true);
        $secondData = json_decode($secondFlow->flow_data, true);
        $firstFormId = collect($firstData['nodes'] ?? [])->firstWhere('type', 'whatsapp_flow')['data']['settings']['whatsappFlowId'] ?? null;
        $secondFormId = collect($secondData['nodes'] ?? [])->firstWhere('type', 'whatsapp_flow')['data']['settings']['whatsappFlowId'] ?? null;

        $this->assertNotEmpty($firstFormId);
        $this->assertSame($firstFormId, $secondFormId);
    }
}
