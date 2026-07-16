<?php

namespace Tests\Unit;

use App\Services\WhatsappFlowComponentMapper;
use Tests\TestCase;

class WhatsappFlowComponentMapperTest extends TestCase
{
    private WhatsappFlowComponentMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new WhatsappFlowComponentMapper;
    }

    public function test_dynamic_field_uses_data_source_key_as_component_name(): void
    {
        $component = $this->mapper->convertFieldToComponent(
            [
                'id' => 99,
                'type' => 'radio',
                'label' => 'Cover',
                'dynamic_data_source' => true,
                'data_source_key' => 'cover',
                'options' => [],
            ],
            'NEXT',
            false,
            fn () => ''
        );

        $this->assertSame('cover', $component['name']);
    }

    public function test_explicit_name_wins_over_data_source_key_for_booking_fields(): void
    {
        $component = $this->mapper->convertFieldToComponent(
            [
                'id' => 10,
                'type' => 'select',
                'label' => 'Service',
                'dynamic_data_source' => true,
                'data_source_key' => 'service_options',
                'name' => 'service',
                'meta_name' => 'service',
                'options' => [],
            ],
            'PICK_SLOT',
            false,
            fn () => ''
        );

        $this->assertSame('service', $component['name']);
        $this->assertSame('${data.service_options}', $component['data-source']);
    }

    public function test_heading_uses_text_when_label_missing(): void
    {
        $component = $this->mapper->convertFieldToComponent(
            [
                'id' => 20,
                'type' => 'heading',
                'text' => 'Available times',
            ],
            'PICK_SLOT',
            false,
            fn () => ''
        );

        $this->assertSame('TextHeading', $component['type']);
        $this->assertSame('Available times', $component['text']);
    }

    public function test_appointment_slot_screen_heading_is_not_blank(): void
    {
        $screens = config('whatsapp-form-templates.appointment_booking.screens');
        $this->assertIsArray($screens);

        $children = $this->mapper->convertFieldsToComponents(
            $screens[1]['fields'],
            null,
            true,
            [],
            fn () => '',
            [],
            array_column($screens[1]['dynamic_data'] ?? [], 'key'),
            [],
            [],
            true
        );

        $heading = $children[0] ?? null;
        $this->assertIsArray($heading);
        $this->assertSame('TextHeading', $heading['type'] ?? null);
        $this->assertNotSame('', trim((string) ($heading['text'] ?? '')));
        $this->assertIsString($heading['text']);
        $this->assertSame('Available times', $heading['text']);
    }

    public function test_on_select_payload_is_preserved_without_auto_injected_component_key(): void
    {
        $component = $this->mapper->convertFieldToComponent(
            [
                'id' => 1,
                'type' => 'radio',
                'label' => 'Payment',
                'dynamic_data_source' => true,
                'data_source_key' => 'payment_method',
                'on_select_action' => 'data_exchange',
                'on_select_payload' => ['payment_option' => '${form.payment_method}'],
                'options' => [],
            ],
            'NEXT',
            false,
            fn () => ''
        );

        $this->assertSame('payment_method', $component['name']);
        $this->assertSame('${form.payment_method}', $component['on-select-action']['payload']['payment_option']);
        $this->assertArrayNotHasKey('payment_method', $component['on-select-action']['payload']);
    }

    public function test_navigate_payload_includes_next_screen_dynamic_data_keys(): void
    {
        $payload = $this->mapper->buildNavigatePayload(
            [
                [
                    'id' => 2,
                    'type' => 'radio',
                    'dynamic_data_source' => true,
                    'data_source_key' => 'cover',
                ],
            ],
            ['additional_applicants_count'],
            ['additional_applicants_count'],
            ['cover', 'additional_applicants_count'],
            [],
            false
        );

        $this->assertSame('${form.cover}', $payload['cover']);
        $this->assertSame('${data.additional_applicants_count}', $payload['additional_applicants_count']);
        $this->assertArrayNotHasKey('radio_99', $payload);
    }

    public function test_footer_preserves_imported_on_click_payload(): void
    {
        $components = $this->mapper->convertFieldsToComponents(
            [
                [
                    'id' => 1,
                    'type' => 'footer',
                    'label' => 'Continue',
                    'on_click_action' => 'navigate',
                    'navigate_next' => 'SCREEN_B',
                    'on_click_payload' => [
                        'cover' => '${form.cover}',
                        'additional_applicants_count' => '${form.additional_applicants_count}',
                    ],
                ],
            ],
            'SCREEN_B',
            false,
            [],
            fn () => '',
            [],
            [],
            [],
            []
        );

        $footer = collect($components)->flatMap(fn ($c) => $c['children'] ?? [$c])->firstWhere('type', 'Footer');

        $this->assertNotNull($footer);
        $this->assertSame('${form.cover}', $footer['on-click-action']['payload']['cover']);
        $this->assertSame('SCREEN_B', $footer['on-click-action']['next']['name']);
    }

    public function test_reconcile_navigate_payload_drops_invalid_data_bindings(): void
    {
        $generated = [
            'radio_201' => '${form.radio_201}',
            'cover' => '${data.cover}',
        ];

        $reconciled = $this->mapper->reconcileNavigatePayload(
            [
                'excess' => '${data.excess}',
                'cover_level' => '${data.cover_level}',
                'radio_201' => '${data.radio_201}',
            ],
            $generated,
            ['cover', 'additional_applicants_count'],
            false
        );

        $this->assertSame($generated, $reconciled);
    }
}
