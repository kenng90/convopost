<?php

namespace Tests\Unit;

use App\Models\WhatsappFlow;
use App\Services\WhatsappFlowComponentMapper;
use App\Services\WhatsappMetaFlowService;
use Tests\TestCase;

class WhatsappMetaFlowServiceTest extends TestCase
{
    private WhatsappMetaFlowService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new WhatsappMetaFlowService(new WhatsappFlowComponentMapper);
    }

    public function test_embedded_link_uses_open_url_action(): void
    {
        $flow = $this->makeFlow([
            [
                'id' => 'SCREEN_A',
                'title' => 'Links',
                'fields' => [
                    ['id' => 1, 'type' => 'embedded_link', 'label' => 'Terms', 'url' => 'https://example.com/terms', 'button_label' => 'Read terms'],
                    ['id' => 2, 'type' => 'footer', 'label' => 'Done'],
                ],
            ],
        ]);

        $meta = $this->service->convertToMetaFormat($flow);
        $formChildren = $meta['screens'][0]['layout']['children'][0]['children'] ?? [];
        $link = collect($formChildren)->firstWhere('type', 'EmbeddedLink');

        $this->assertNotNull($link);
        $this->assertSame('open_url', $link['on-click-action']['name']);
        $this->assertSame('https://example.com/terms', $link['on-click-action']['url']);
    }

    public function test_chips_exports_as_chips_selector_with_array_meta_type(): void
    {
        $flow = $this->makeFlow([
            [
                'id' => 'SCREEN_A',
                'title' => 'Chips',
                'fields' => [
                    [
                        'id' => 1,
                        'type' => 'chips',
                        'label' => 'Interests',
                        'required' => true,
                        'meta_type' => 'array',
                        'meta_example' => ['a'],
                        'options' => [
                            ['id' => 'a', 'label' => 'Sports', 'value' => 'sports'],
                            ['id' => 'b', 'label' => 'Music', 'value' => 'music'],
                        ],
                    ],
                    ['id' => 2, 'type' => 'footer', 'label' => 'Submit'],
                ],
            ],
        ]);

        $meta = $this->service->convertToMetaFormat($flow);
        $formChildren = $meta['screens'][0]['layout']['children'][0]['children'] ?? [];
        $chips = collect($formChildren)->firstWhere('type', 'ChipsSelector');

        $this->assertNotNull($chips);
        $this->assertSame('chips_1', $chips['name']);
        $this->assertCount(2, $chips['data-source']);
    }

    public function test_photo_picker_is_not_included_in_navigate_payload(): void
    {
        $flow = $this->makeFlow([
            [
                'id' => 'SCREEN_A',
                'title' => 'Upload',
                'fields' => [
                    ['id' => 1, 'type' => 'photo_picker', 'label' => 'ID photo', 'meta_type' => 'array', 'meta_example' => []],
                    ['id' => 2, 'type' => 'footer', 'label' => 'Next'],
                ],
            ],
            [
                'id' => 'SCREEN_B',
                'title' => 'Done',
                'fields' => [
                    ['id' => 3, 'type' => 'footer', 'label' => 'Submit'],
                ],
            ],
        ]);

        $meta = $this->service->convertToMetaFormat($flow);
        $footer = collect($meta['screens'][0]['layout']['children'][0]['children'] ?? [])
            ->firstWhere('type', 'Footer');

        $this->assertNotNull($footer);
        $this->assertSame('data_exchange', $footer['on-click-action']['name']);
        $this->assertArrayNotHasKey('photo_1', (array) $footer['on-click-action']['payload']);
    }

    public function test_validation_catches_navigation_list_with_extra_components(): void
    {
        $flow = $this->makeFlow([
            [
                'id' => 'SCREEN_A',
                'title' => 'Nav',
                'is_terminal' => true,
                'fields' => [
                    ['id' => 1, 'type' => 'navigation_list', 'list_items' => [['id' => 'a', 'title' => 'Go']]],
                    ['id' => 2, 'type' => 'heading', 'label' => 'Extra'],
                ],
            ],
        ]);

        $result = $this->service->getPublishValidation($flow);

        $this->assertNotEmpty($result['errors']);
        $navigationListError = collect($result['errors'])->first(
            fn (string $error) => str_contains($error, 'NavigationList')
        );
        $this->assertNotNull($navigationListError);
    }

    public function test_booking_flow_dynamic_slot_select_passes_validation_without_static_options(): void
    {
        $flow = $this->makeFlow([
            [
                'id' => 'BOOKING',
                'title' => 'Book appointment',
                'endpoint_template' => 'booking_slots',
                'terminal' => true,
                'dynamic_data' => [
                    ['key' => 'available_slots', 'type' => 'option_list', 'example_items' => []],
                    ['key' => 'is_dropdown_visible', 'type' => 'boolean', 'example' => false],
                ],
                'fields' => [
                    [
                        'id' => 1,
                        'type' => 'date',
                        'label' => 'Select date',
                        'on_select_action' => 'data_exchange',
                        'on_select_payload' => ['component_action' => 'update_date'],
                    ],
                    [
                        'id' => 2,
                        'type' => 'select',
                        'label' => 'Pick a time slot',
                        'dynamic_data_source' => true,
                        'data_source_key' => 'available_slots',
                        'options' => [],
                    ],
                    ['id' => 3, 'type' => 'footer', 'label' => 'Confirm booking'],
                ],
            ],
        ]);

        $result = $this->service->getPublishValidation($flow);

        $this->assertSame([], $result['errors']);
    }

    public function test_dynamic_data_entries_are_merged_into_screen_data(): void
    {
        $flow = $this->makeFlow([
            [
                'id' => 'BOOKING',
                'title' => 'Book',
                'dynamic_data' => [
                    ['key' => 'available_slots', 'type' => 'option_list', 'example_items' => []],
                    ['key' => 'is_dropdown_visible', 'type' => 'boolean', 'example' => false],
                ],
                'fields' => [
                    [
                        'id' => 1,
                        'type' => 'date',
                        'label' => 'Date',
                        'on_select_action' => 'data_exchange',
                        'on_select_payload' => ['component_action' => 'update_date'],
                    ],
                    ['id' => 2, 'type' => 'footer', 'label' => 'Book'],
                ],
            ],
        ]);

        $meta = $this->service->convertToMetaFormat($flow);
        $screen = $meta['screens'][0];

        $this->assertArrayHasKey('available_slots', (array) $screen['data']);
        $this->assertArrayHasKey('is_dropdown_visible', (array) $screen['data']);
        $this->assertSame('3.0', $meta['data_api_version']);

        $datePicker = collect($meta['screens'][0]['layout']['children'][0]['children'] ?? [])
            ->firstWhere('type', 'DatePicker');
        $this->assertSame('data_exchange', $datePicker['on-select-action']['name']);
        $this->assertSame('update_date', $datePicker['on-select-action']['payload']['component_action']);
    }

    public function test_calendar_range_mode_uses_object_label_and_required(): void
    {
        $mapper = new WhatsappFlowComponentMapper;
        $component = $mapper->convertFieldToComponent(
            [
                'id' => 11,
                'type' => 'calendar',
                'label' => 'Trip dates',
                'calendar_mode' => 'range',
                'label_start' => 'Check-in',
                'label_end' => 'Check-out',
                'required' => true,
            ],
            null,
            false,
            fn () => ''
        );

        $this->assertSame('range', $component['mode']);
        $this->assertSame(['start-date' => 'Check-in', 'end-date' => 'Check-out'], $component['label']);
        $this->assertSame(['start-date' => true, 'end-date' => true], $component['required']);
    }

    public function test_navigate_payload_uses_component_names_expected_by_next_screen(): void
    {
        $flow = $this->makeFlow([
            [
                'id' => 'APPLICANTS',
                'title' => 'Applicants',
                'dynamic_data' => [
                    ['key' => 'cover', 'type' => 'string', 'example' => 'Myself'],
                    ['key' => 'additional_applicants_count', 'type' => 'string', 'example' => '0'],
                ],
                'fields' => [
                    [
                        'id' => 200,
                        'type' => 'radio',
                        'label' => 'Cover',
                        'dynamic_data_source' => true,
                        'data_source_key' => 'cover',
                        'options' => [],
                    ],
                    ['id' => 201, 'type' => 'footer', 'label' => 'Continue'],
                ],
            ],
            [
                'id' => 'COVER_LEVEL',
                'title' => 'Cover level',
                'dynamic_data' => [
                    ['key' => 'cover', 'type' => 'string', 'example' => 'Myself'],
                    ['key' => 'additional_applicants_count', 'type' => 'string', 'example' => '0'],
                ],
                'fields' => [
                    ['id' => 201, 'type' => 'radio', 'label' => 'Level', 'options' => []],
                    [
                        'id' => 202,
                        'type' => 'footer',
                        'label' => 'Continue',
                        'on_click_action' => 'navigate',
                        'navigate_next' => 'EXCESS',
                        'on_click_payload' => [
                            'excess' => '${data.excess}',
                            'cover_level' => '${data.cover_level}',
                            'cover' => '${data.cover}',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'EXCESS',
                'title' => 'Excess',
                'dynamic_data' => [
                    ['key' => 'excess', 'type' => 'string', 'example' => '£250'],
                    ['key' => 'cover_level', 'type' => 'string', 'example' => 'Standard'],
                    ['key' => 'cover', 'type' => 'string', 'example' => 'Myself'],
                    ['key' => 'additional_applicants_count', 'type' => 'string', 'example' => '0'],
                ],
                'fields' => [
                    ['id' => 205, 'type' => 'select', 'label' => 'Excess', 'data_source_key' => 'excess', 'options' => []],
                    ['id' => 206, 'type' => 'footer', 'label' => 'Continue'],
                ],
            ],
        ]);

        $meta = $this->service->convertToMetaFormat($flow);
        $coverLevelScreen = $meta['screens'][1];
        $footer = collect($coverLevelScreen['layout']['children'][0]['children'] ?? [])
            ->firstWhere('type', 'Footer');

        $this->assertNotNull($footer);
        $payload = (array) $footer['on-click-action']['payload'];

        $this->assertArrayHasKey('cover_level', $payload);
        $this->assertSame('${form.radio_201}', $payload['cover_level']);
        $this->assertArrayHasKey('excess', $payload);
        $this->assertSame('${data.excess}', $payload['excess']);
        $this->assertArrayHasKey('cover', $payload);
        $this->assertSame('${data.cover}', $payload['cover']);
        $this->assertArrayHasKey('excess', (array) $coverLevelScreen['data']);
        $this->assertSame('data_exchange', $footer['on-click-action']['name']);
        $this->assertArrayNotHasKey('next', $footer['on-click-action']);
    }

    public function test_endpoint_flow_footers_use_data_exchange_without_next(): void
    {
        $flow = $this->makeFlow([
            [
                'id' => 'SCREEN_A',
                'title' => 'Start',
                'dynamic_data' => [
                    ['key' => 'items', 'type' => 'option_list', 'example_items' => []],
                ],
                'fields' => [
                    ['id' => 1, 'type' => 'text', 'label' => 'Name'],
                    ['id' => 2, 'type' => 'footer', 'label' => 'Continue'],
                ],
            ],
            [
                'id' => 'SCREEN_B',
                'title' => 'Done',
                'is_terminal' => true,
                'fields' => [
                    ['id' => 3, 'type' => 'footer', 'label' => 'Submit'],
                ],
            ],
        ]);

        $meta = $this->service->convertToMetaFormat($flow);
        $footer = collect($meta['screens'][0]['layout']['children'][0]['children'] ?? [])
            ->firstWhere('type', 'Footer');

        $this->assertNotNull($footer);
        $this->assertSame('data_exchange', $footer['on-click-action']['name']);
        $this->assertArrayNotHasKey('next', $footer['on-click-action']);
    }

    public function test_textarea_default_max_length_is_six_hundred(): void
    {
        $mapper = new WhatsappFlowComponentMapper;
        $component = $mapper->convertFieldToComponent(
            ['id' => 1, 'type' => 'textarea', 'label' => 'Notes'],
            null,
            true,
            fn () => ''
        );

        $this->assertSame(600, $component['max-length']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $screens
     */
    private function makeFlow(array $screens): WhatsappFlow
    {
        return new WhatsappFlow([
            'id' => 1,
            'name' => 'Test Flow',
            'flow_json' => ['screens' => $screens],
        ]);
    }
}
