<?php

namespace App\Services\Flowmaker;

/**
 * Declares which Flowmaker node types are WhatsApp-native vs omni-safe.
 */
class FlowChannelCompatibility
{
    public const WHATSAPP_ONLY_TYPES = [
        'template',
        'whatsapp_flow',
        'whatsapp_catalog',
        'listing_inquiry',
        'catalog_search',
    ];

    public const OMNI_SAFE_TYPES = [
        'keyword_trigger',
        'incomingMessage',
        'incoming_message',
        'message',
        'image',
        'quick_replies',
        'list_message',
        'question',
        'branch',
        'openai',
        'http',
        'set_variable',
        'datastore',
        'counter',
        'assign_agent',
        'assign_group',
        'assign_journey_stage',
        'end',
    ];

    public const SUPPORTED_FLOW_CHANNELS = [
        'whatsapp',
        'instagram',
        'messenger',
    ];

    /**
     * @param  array<string, mixed>  $flowData
     * @return array{whatsapp_only_nodes: array<int, array{id: string, type: string}>, warnings: array<int, string>}
     */
    public function analyze(array $flowData): array
    {
        $whatsappOnly = [];
        $warnings = [];

        foreach ($flowData['nodes'] ?? [] as $node) {
            $id = (string) ($node['id'] ?? '');
            $type = (string) ($node['type'] ?? ($node['data']['type'] ?? ''));

            if ($id === '' || $type === '') {
                continue;
            }

            if (in_array($type, self::WHATSAPP_ONLY_TYPES, true)) {
                $whatsappOnly[] = ['id' => $id, 'type' => $type];
                $warnings[] = "Node [{$id}] ({$type}) is WhatsApp-only — Instagram/Messenger will skip it or use a text fallback.";
            }
        }

        $supported = $flowData['supported_channels']
            ?? $flowData['meta']['supported_channels']
            ?? self::SUPPORTED_FLOW_CHANNELS;

        if (is_array($supported) && $whatsappOnly !== []) {
            $nonWhatsapp = array_values(array_filter(
                $supported,
                fn ($channel) => is_string($channel) && $channel !== 'whatsapp'
            ));

            if ($nonWhatsapp !== []) {
                $warnings[] = 'This flow includes WhatsApp-only nodes but lists non-WhatsApp channels ('.implode(', ', $nonWhatsapp).'). Those steps will not run natively on Instagram/Messenger.';
            }
        }

        return [
            'whatsapp_only_nodes' => $whatsappOnly,
            'warnings' => $warnings,
        ];
    }

    public function isWhatsappOnlyType(string $type): bool
    {
        return in_array($type, self::WHATSAPP_ONLY_TYPES, true);
    }
}
