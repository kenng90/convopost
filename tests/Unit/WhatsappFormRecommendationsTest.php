<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\WhatsappFlow;
use App\Services\Flowmaker\FlowSimulateService;
use App\Services\Flowmaker\WhatsappFormAutomationFactory;
use App\Services\Flowmaker\WhatsappFormFieldMapper;
use App\Services\WhatsappFlows\WhatsappFlowCommercePrefillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contacts\Models\Field;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\Nodes\WhatsAppFlow as WhatsAppFlowNode;
use ReflectionMethod;
use Tests\TestCase;

class WhatsappFormRecommendationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_field_mapper_suggests_crm_mappings_by_alias(): void
    {
        $company = Company::factory()->create();
        Field::create(['company_id' => $company->id, 'name' => 'Email', 'type' => 'text']);
        Field::create(['company_id' => $company->id, 'name' => 'Budget', 'type' => 'text']);

        $form = WhatsappFlow::create([
            'company_id' => $company->id,
            'name' => 'Lead',
            'status' => 'published',
            'meta_flow_id' => 'M1',
            'flow_json' => [
                'screens' => [[
                    'id' => 'S1',
                    'title' => 'Info',
                    'fields' => [
                        ['id' => 1, 'type' => 'text', 'name' => 'email', 'label' => 'Email Address'],
                        ['id' => 2, 'type' => 'text', 'name' => 'budget', 'label' => 'Monthly Budget'],
                        [
                            'id' => 3,
                            'type' => 'select',
                            'name' => 'interest',
                            'label' => 'Interest',
                            'options' => [
                                ['id' => 'vip', 'title' => 'VIP'],
                                ['id' => 'standard', 'title' => 'Standard'],
                            ],
                        ],
                    ],
                ]],
            ],
        ]);

        $mapper = app(WhatsappFormFieldMapper::class);
        $mappings = $mapper->suggestCrmMappings($form, $company->id);
        $this->assertNotEmpty($mappings);
        $this->assertTrue(collect($mappings)->contains(fn ($m) => $m['formFieldKey'] === 'email'));

        $conditions = $mapper->suggestLeadConditions($form);
        $this->assertCount(2, $conditions);
        $this->assertSame('interest', $conditions[0]['fieldName']);

        $this->assertSame('budget', $mapper->suggestAmountFieldKey($form));
    }

    public function test_factory_lead_recipe_includes_mappings_and_separated_else(): void
    {
        $company = Company::factory()->create();
        Field::create(['company_id' => $company->id, 'name' => 'Email', 'type' => 'text']);

        $form = WhatsappFlow::create([
            'company_id' => $company->id,
            'name' => 'Lead Form',
            'status' => 'published',
            'meta_flow_id' => 'META-1',
            'flow_json' => [
                'screens' => [[
                    'fields' => [
                        ['id' => 1, 'type' => 'text', 'name' => 'email', 'label' => 'Email'],
                        [
                            'id' => 2,
                            'type' => 'radio',
                            'name' => 'plan',
                            'label' => 'Plan',
                            'options' => [
                                ['id' => 'a', 'title' => 'A'],
                                ['id' => 'b', 'title' => 'B'],
                            ],
                        ],
                    ],
                ]],
            ],
        ]);

        $flow = app(WhatsappFormAutomationFactory::class)->createFromForm($form, 'lead', $company->id);
        $data = json_decode($flow->draft_flow_data, true);
        $node = collect($data['nodes'])->firstWhere('type', 'whatsapp_flow');

        $this->assertNotEmpty($node['data']['settings']['fieldMappings']);
        $this->assertNotEmpty($node['data']['settings']['conditions']);
        $this->assertTrue(collect($data['nodes'])->contains(fn ($n) => ($n['id'] ?? '') === 'message-no-match'));
        $this->assertTrue(
            collect($data['edges'])->contains(fn ($e) => ($e['sourceHandle'] ?? '') === 'else' && ($e['target'] ?? '') === 'message-no-match')
        );
    }

    public function test_factory_checkout_binds_amount_variable(): void
    {
        $company = Company::factory()->create();
        $form = WhatsappFlow::create([
            'company_id' => $company->id,
            'name' => 'Checkout',
            'status' => 'published',
            'meta_flow_id' => 'META-2',
            'flow_json' => [
                'screens' => [[
                    'fields' => [
                        ['id' => 1, 'type' => 'text', 'name' => 'amount', 'label' => 'Amount'],
                    ],
                ]],
            ],
        ]);

        $flow = app(WhatsappFormAutomationFactory::class)->createFromForm($form, 'checkout', $company->id);
        $data = json_decode($flow->draft_flow_data, true);
        $pay = collect($data['nodes'])->firstWhere('type', 'request_payment');

        $this->assertSame('{{form_amount}}', $pay['data']['settings']['payment']['amount']);
    }

    public function test_score_rules_route_to_score_pass_handle(): void
    {
        $node = new WhatsAppFlowNode(
            ['id' => 'n1', 'type' => 'whatsapp_flow', 'data' => ['label' => 'Form']],
            []
        );
        $method = new ReflectionMethod(WhatsAppFlowNode::class, 'computeResponseScore');
        $method->setAccessible(true);

        $score = $method->invoke($node, [
            ['fieldName' => 'budget', 'operator' => 'gte', 'value' => '1000', 'points' => 10],
            ['fieldName' => 'city', 'operator' => '==', 'value' => 'nairobi', 'points' => 15],
        ], ['budget' => '5000', 'city' => 'nairobi']);

        $this->assertSame(25.0, $score);
    }

    public function test_condition_all_of_includes_primary_clause(): void
    {
        $node = new WhatsAppFlowNode(
            ['id' => 'n1', 'type' => 'whatsapp_flow', 'data' => ['label' => 'Form']],
            []
        );
        $method = new ReflectionMethod(WhatsAppFlowNode::class, 'evaluateCondition');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($node, [
            'fieldName' => 'budget',
            'operator' => 'gte',
            'value' => '10',
            'allOf' => [
                ['fieldName' => 'city', 'operator' => '==', 'value' => 'nairobi'],
            ],
        ], ['budget' => '10', 'city' => 'nairobi']));

        $this->assertFalse($method->invoke($node, [
            'fieldName' => 'budget',
            'operator' => 'gte',
            'value' => '10',
            'allOf' => [
                ['fieldName' => 'city', 'operator' => '==', 'value' => 'kisumu'],
            ],
        ], ['budget' => '10', 'city' => 'nairobi']));
    }

    public function test_simulate_form_completed_follows_on_flow_completed(): void
    {
        $payload = [
            'nodes' => [
                [
                    'id' => 'kw',
                    'type' => 'keyword_trigger',
                    'data' => ['keywords' => [['value' => 'hi', 'matchType' => 'contains']]],
                ],
                [
                    'id' => 'form',
                    'type' => 'whatsapp_flow',
                    'data' => ['settings' => ['whatsappFlowId' => 1]],
                ],
                [
                    'id' => 'thanks',
                    'type' => 'message',
                    'data' => ['label' => 'Thanks', 'settings' => ['message' => 'ok']],
                ],
                ['id' => 'end', 'type' => 'end', 'data' => ['label' => 'End']],
            ],
            'edges' => [
                ['source' => 'kw', 'target' => 'form', 'sourceHandle' => 'keyword-1'],
                ['source' => 'form', 'target' => 'thanks', 'sourceHandle' => 'onFlowCompleted'],
                ['source' => 'thanks', 'target' => 'end'],
            ],
        ];

        $result = app(FlowSimulateService::class)->simulate($payload, 'hi there', 'form_completed');
        $types = collect($result['path'])->pluck('type')->all();

        $this->assertContains('whatsapp_flow', $types);
        $this->assertContains('message', $types);
        $this->assertArrayHasKey('form_sim_field', $result['variables']);
    }

    public function test_commerce_prefill_reads_catalog_state(): void
    {
        $company = Company::factory()->create();
        $contact = Contact::create([
            'company_id' => $company->id,
            'name' => 'Buyer',
            'phone' => '254700000099',
        ]);
        $contact->setContactState(55, 'catalog_order_total_amount', '2500');
        $contact->setContactState(55, 'listing_booking_item_title', 'Studio Apt');

        $form = WhatsappFlow::create([
            'company_id' => $company->id,
            'name' => 'Confirm',
            'status' => 'draft',
            'flow_json' => [
                'screens' => [[
                    'id' => 'S1',
                    'dynamic_data' => [
                        ['key' => 'amount'],
                        ['key' => 'product_title'],
                    ],
                    'fields' => [],
                ]],
            ],
        ]);

        $prefill = app(WhatsappFlowCommercePrefillService::class)
            ->buildPrefillData($form, $contact, 55);

        $this->assertSame('2500', $prefill['amount']);
        $this->assertSame('Studio Apt', $prefill['product_title']);
    }
}
