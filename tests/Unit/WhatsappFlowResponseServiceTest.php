<?php

namespace Tests\Unit;

use App\Models\WhatsappFlow;
use App\Models\WhatsappFlowResponse;
use App\Services\WhatsappFlowResponseService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WhatsappFlowResponseServiceTest extends TestCase
{
    private WhatsappFlowResponseService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new WhatsappFlowResponseService;
    }

    public function test_it_resolves_option_labels_and_groups_responses_by_screen(): void
    {
        $flow = $this->makeFlow();

        $responses = [
            'text_1' => 'Jane Doe',
            'radio_2' => 'option_1',
            'flow_token' => 'should-be-removed',
        ];

        $flat = $this->service->enrichResponsesFlat($responses, $flow);

        $this->assertCount(2, $flat);
        $this->assertSame('Full Name', $flat[0]['label']);
        $this->assertSame('Jane Doe', $flat[0]['display_value']);
        $this->assertSame('Service Type', $flat[1]['label']);
        $this->assertSame('Cleaning', $flat[1]['display_value']);

        $grouped = $this->service->groupResponsesByScreen($responses, $flow);

        $this->assertCount(2, $grouped);
        $this->assertSame('Personal Details', $grouped[0]['title']);
        $this->assertSame('Request Details', $grouped[1]['title']);
    }

    public function test_it_builds_table_rows_and_preview_text(): void
    {
        $flow = $this->makeFlow();

        $response = $this->makeResponse($flow, [
            'text_1' => 'Jane Doe',
            'radio_2' => 'option_1',
            'date_3' => '2026-03-15',
        ]);

        $rows = $this->service->buildTableRows(collect([$response]), $flow);

        $this->assertSame('Jane Doe', $rows[0]['cells']['text_1']);
        $this->assertSame('Cleaning', $rows[0]['cells']['radio_2']);
        $this->assertSame('15 Mar 2026', $rows[0]['cells']['date_3']);
        $this->assertSame('Jane Doe · Cleaning', $rows[0]['preview']);
        $this->assertSame(134, $rows[0]['duration_seconds']);
    }

    public function test_it_computes_choice_and_funnel_analytics(): void
    {
        $flow = $this->makeFlow();

        $responses = collect([
            $this->makeResponse($flow, ['text_1' => 'Jane', 'radio_2' => 'option_1'], 'completed'),
            $this->makeResponse($flow, ['text_1' => 'John', 'radio_2' => 'option_2'], 'completed'),
            $this->makeResponse($flow, ['text_1' => 'Alex'], 'abandoned'),
        ]);

        $choiceAnalytics = $this->service->computeChoiceAnalytics($responses, $flow);

        $this->assertCount(1, $choiceAnalytics);
        $this->assertSame('Service Type', $choiceAnalytics[0]['label']);
        $this->assertSame(2, $choiceAnalytics[0]['total']);
        $this->assertSame('Cleaning', $choiceAnalytics[0]['options'][0]['label']);
        $this->assertSame(1, $choiceAnalytics[0]['options'][0]['count']);

        $funnel = $this->service->computeFunnelAnalytics($responses, $flow);

        $this->assertSame('Flow sent', $funnel[0]['label']);
        $this->assertSame(3, $funnel[0]['count']);
        $this->assertSame('Completed', $funnel[count($funnel) - 1]['label']);
        $this->assertSame(2, $funnel[count($funnel) - 1]['count']);
    }

    public function test_it_formats_checkbox_and_optin_values(): void
    {
        $flow = $this->makeFlow([
            'screens' => [
                [
                    'id' => 'SCREEN_A',
                    'title' => 'Preferences',
                    'fields' => [
                        [
                            'id' => 4,
                            'type' => 'checkbox',
                            'label' => 'Interests',
                            'options' => [
                                ['id' => 'opt1', 'label' => 'SMS Updates', 'value' => 'sms'],
                                ['id' => 'opt2', 'label' => 'Email Updates', 'value' => 'email'],
                            ],
                        ],
                        [
                            'id' => 5,
                            'type' => 'optin',
                            'label' => 'Terms Accepted',
                        ],
                    ],
                ],
            ],
        ]);

        $display = $this->service->formatDisplayValue(['sms', 'email'], $flow->flow_json['screens'][0]['fields'][0]);
        $this->assertSame('SMS Updates, Email Updates', $display);
        $this->assertSame('Yes', $this->service->formatDisplayValue(true, $flow->flow_json['screens'][0]['fields'][1]));
    }

    private function makeFlow(?array $flowJson = null): WhatsappFlow
    {
        $flow = new WhatsappFlow([
            'company_id' => 1,
            'name' => 'Booking Form',
            'category' => 'OTHER',
            'flow_json' => $flowJson ?? [
                'screens' => [
                    [
                        'id' => 'SCREEN_A',
                        'title' => 'Personal Details',
                        'fields' => [
                            [
                                'id' => 1,
                                'type' => 'text',
                                'label' => 'Full Name',
                            ],
                        ],
                    ],
                    [
                        'id' => 'SCREEN_B',
                        'title' => 'Request Details',
                        'fields' => [
                            [
                                'id' => 2,
                                'type' => 'radio',
                                'label' => 'Service Type',
                                'options' => [
                                    ['id' => 'opt1', 'label' => 'Cleaning', 'value' => 'option_1'],
                                    ['id' => 'opt2', 'label' => 'Laundry', 'value' => 'option_2'],
                                ],
                            ],
                            [
                                'id' => 3,
                                'type' => 'date',
                                'label' => 'Preferred Date',
                            ],
                        ],
                    ],
                ],
            ],
            'status' => 'published',
        ]);
        $flow->id = 1;

        return $flow;
    }

    /**
     * @param  array<string, mixed>  $responses
     */
    private function makeResponse(WhatsappFlow $flow, array $responses, string $status = 'completed'): WhatsappFlowResponse
    {
        $response = new WhatsappFlowResponse([
            'company_id' => 1,
            'whatsapp_flow_id' => $flow->id,
            'contact_name' => 'Jane Doe',
            'contact_phone' => '+254712345678',
            'responses' => $responses,
            'status' => $status,
            'sent_at' => Carbon::parse('2026-03-08 14:00:00'),
            'completed_at' => Carbon::parse('2026-03-08 14:02:14'),
        ]);
        $response->id = random_int(100, 999);

        return $response;
    }
}
