<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\WhatsappFlow;
use App\Services\Flowmaker\FormConversionAnalyticsService;
use App\Services\Flowmaker\WhatsappFormAutomationFactory;
use App\Services\WhatsappFlowReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Flowmaker\Models\Flow;
use Modules\Flowmaker\Models\FlowRunLog;
use Modules\Flowmaker\Models\Nodes\WhatsAppFlow as WhatsAppFlowNode;
use ReflectionMethod;
use Tests\TestCase;

class WhatsappFlowValueLoopTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_lead_recipe_bound_to_live_form(): void
    {
        $company = Company::factory()->create();
        $form = WhatsappFlow::create([
            'company_id' => $company->id,
            'name' => 'Lead Form',
            'flow_json' => ['screens' => [['fields' => [['type' => 'text']]]]],
            'status' => 'published',
            'meta_flow_id' => 'META-123',
        ]);

        $flow = app(WhatsappFormAutomationFactory::class)->createFromForm($form, 'lead', $company->id);

        $this->assertSame('whatsapp_form_lead', $flow->source_template);
        $this->assertTrue($flow->exclusive_on_match);
        $data = json_decode($flow->draft_flow_data, true);
        $node = collect($data['nodes'])->firstWhere('type', 'whatsapp_flow');
        $this->assertSame($form->id, (int) $node['data']['settings']['whatsappFlowId']);
        $this->assertTrue(
            collect($data['edges'])->contains(fn ($e) => ($e['sourceHandle'] ?? '') === 'onAbandoned')
        );
    }

    public function test_factory_rejects_non_live_form(): void
    {
        $company = Company::factory()->create();
        $form = WhatsappFlow::create([
            'company_id' => $company->id,
            'name' => 'Draft Form',
            'flow_json' => ['screens' => [['fields' => [['type' => 'text']]]]],
            'status' => 'draft',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(WhatsappFormAutomationFactory::class)->createFromForm($form, 'lead', $company->id);
    }

    public function test_readiness_reports_live_step(): void
    {
        $company = Company::factory()->create();
        $company->setMultipleConfig([
            'whatsapp_permanent_access_token' => 'token',
            'whatsapp_business_account_id' => 'waba',
            'whatsapp_phone_number_id' => 'phone',
        ]);

        $form = WhatsappFlow::create([
            'company_id' => $company->id,
            'name' => 'Ready Form',
            'flow_json' => ['screens' => [['fields' => [['type' => 'text', 'id' => 1]]]]],
            'status' => 'draft',
            'meta_flow_id' => null,
        ]);

        $summary = app(WhatsappFlowReadinessService::class)->forForm($form->fresh('company'));
        $this->assertTrue($summary['can_publish']);
        $this->assertFalse($summary['live']);
        $this->assertFalse($summary['ready']);
    }

    public function test_condition_operators_numeric_and_in(): void
    {
        $node = new WhatsAppFlowNode(
            ['id' => 'n1', 'type' => 'whatsapp_flow', 'data' => ['label' => 'Form']],
            []
        );
        $method = new ReflectionMethod(WhatsAppFlowNode::class, 'evaluateCondition');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($node, [
            'fieldName' => 'amount',
            'operator' => 'gt',
            'value' => '10',
        ], ['amount' => '25']));

        $this->assertTrue($method->invoke($node, [
            'fieldName' => 'city',
            'operator' => 'in',
            'value' => 'nairobi, mombasa',
        ], ['city' => 'Nairobi']));

        $this->assertTrue($method->invoke($node, [
            'allOf' => [
                ['fieldName' => 'amount', 'operator' => 'gte', 'value' => '10'],
                ['fieldName' => 'city', 'operator' => '==', 'value' => 'nairobi'],
            ],
        ], ['amount' => '10', 'city' => 'nairobi']));

        $this->assertFalse($method->invoke($node, [
            'allOf' => [
                ['fieldName' => 'amount', 'operator' => 'gte', 'value' => '10'],
                ['fieldName' => 'city', 'operator' => '==', 'value' => 'kisumu'],
            ],
        ], ['amount' => '10', 'city' => 'nairobi']));
    }

    public function test_conversion_analytics_counts_responses_and_paid(): void
    {
        $company = Company::factory()->create();
        $form = WhatsappFlow::create([
            'company_id' => $company->id,
            'name' => 'Shop Form',
            'flow_json' => ['screens' => []],
            'status' => 'published',
            'meta_flow_id' => 'M1',
        ]);

        $automation = Flow::withoutGlobalScopes()->create([
            'name' => 'Shop Auto',
            'company_id' => $company->id,
            'flow_data' => '{}',
        ]);

        \App\Models\WhatsappFlowResponse::create([
            'company_id' => $company->id,
            'whatsapp_flow_id' => $form->id,
            'flow_id' => $automation->id,
            'status' => 'completed',
            'sent_at' => now(),
            'completed_at' => now(),
        ]);
        \App\Models\WhatsappFlowResponse::create([
            'company_id' => $company->id,
            'whatsapp_flow_id' => $form->id,
            'flow_id' => $automation->id,
            'status' => 'abandoned',
            'sent_at' => now()->subDay(),
        ]);

        FlowRunLog::create([
            'flow_id' => $automation->id,
            'contact_id' => 1,
            'event' => 'payment_succeeded',
            'node_id' => 'pay',
        ]);

        $summary = app(FormConversionAnalyticsService::class)->summaryForForm($form->id);

        $this->assertSame(2, $summary['totals']['sent']);
        $this->assertSame(1, $summary['totals']['completed']);
        $this->assertSame(1, $summary['totals']['abandoned']);
        $this->assertSame(1, $summary['totals']['converted']);
        $this->assertSame(50.0, $summary['completion_rate']);
        $this->assertSame(100.0, $summary['conversion_rate']);
    }

    public function test_lifecycle_label_prefers_live(): void
    {
        $form = new WhatsappFlow([
            'status' => 'published',
            'meta_flow_id' => '123',
        ]);
        $this->assertSame('Live on WhatsApp', $form->getLifecycleLabel());

        $draft = new WhatsappFlow([
            'status' => 'draft',
            'meta_flow_id' => null,
        ]);
        $this->assertSame('Draft', $draft->getLifecycleLabel());
    }

    public function test_crm_mapping_writes_contact_field_pivot(): void
    {
        $company = Company::factory()->create();
        $field = \Modules\Contacts\Models\Field::create([
            'company_id' => $company->id,
            'name' => 'Budget',
            'type' => 'text',
        ]);
        $contact = \Modules\Wpbox\Models\Contact::create([
            'company_id' => $company->id,
            'name' => 'Jane',
            'phone' => '254700000001',
        ]);

        $node = new WhatsAppFlowNode(
            ['id' => 'n1', 'type' => 'whatsapp_flow', 'data' => [
                'settings' => [
                    'fieldMappings' => [
                        ['formFieldKey' => 'text_1', 'contactFieldId' => $field->id],
                    ],
                ],
            ]],
            []
        );

        $method = new ReflectionMethod(WhatsAppFlowNode::class, 'applyCrmMappings');
        $method->setAccessible(true);
        $method->invoke($node, $contact, ['text_1' => '50000'], $node->getDataAsArray()['settings']);

        $this->assertSame(
            '50000',
            $contact->fields()->where('custom_contacts_fields.id', $field->id)->first()->pivot->value
        );
    }
}
