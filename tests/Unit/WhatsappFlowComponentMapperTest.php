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
