<?php

namespace Tests\Unit;

use App\Models\WhatsappFlow;
use App\Services\WhatsappFlowSubmissionService;
use App\Services\WhatsappFormTemplateService;
use Tests\TestCase;

class WhatsappFlowSubmissionServiceTest extends TestCase
{
    public function test_it_exposes_field_options_for_a_form(): void
    {
        $flow = $this->makeFlow();

        $fields = app(WhatsappFlowSubmissionService::class)->getFieldOptionsForForm($flow);

        $this->assertSame('text_1', $fields[0]['key']);
        $this->assertSame('Full Name', $fields[0]['label']);
    }

    public function test_form_template_service_lists_templates_and_bundles(): void
    {
        $service = app(WhatsappFormTemplateService::class);

        $this->assertNotEmpty($service->listForGallery());
        $this->assertNotNull($service->get('lead_capture'));
        $this->assertSame('healthcare_appointment', $service->bundleKeyForAutomation('healthcare_clinic_bot'));
        $this->assertSame('microfinance_loan_application', $service->bundleKeyForAutomation('microfinance_banking_bot'));
    }

    public function test_init_data_for_screen_returns_dynamic_examples(): void
    {
        $flow = new WhatsappFlow([
            'flow_json' => [
                'screens' => [[
                    'id' => 'SCREEN_A',
                    'dynamic_data' => [[
                        'key' => 'departments',
                        'type' => 'option_list',
                        'example_items' => [['id' => '1', 'title' => 'General']],
                    ]],
                ]],
            ],
        ]);

        $data = app(\App\Services\WhatsappFlows\WhatsappFlowScreenInitService::class)
            ->initDataForScreen($flow, 'SCREEN_A');

        $this->assertArrayHasKey('departments', $data);
        $this->assertIsArray($data['departments']);
    }

    private function makeFlow(): WhatsappFlow
    {
        $flow = new WhatsappFlow([
            'company_id' => 1,
            'name' => 'Test Form',
            'flow_json' => [
                'screens' => [[
                    'id' => 'S1',
                    'title' => 'Details',
                    'fields' => [
                        ['id' => 1, 'type' => 'text', 'label' => 'Full Name'],
                    ],
                ]],
            ],
        ]);
        $flow->id = 1;

        return $flow;
    }
}
