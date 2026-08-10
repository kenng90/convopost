<?php

namespace App\Services\Flowmaker;

/**
 * Builds Instagram/Messenger-friendly template clones from WhatsApp sample flows.
 *
 * Chat-native WhatsApp widgets are rewritten to online links (booking pages,
 * shop links) plus shared menus/FAQ/handoff that work on every channel.
 */
class FlowTemplateOmniConverter
{
    /**
     * Node types converted to online booking-link nodes.
     *
     * @var array<int, string>
     */
    public const BOOKING_ONLINE_TYPES = [
        'book_appointment',
        'manage_booking',
        'manage_event_registration',
        'booking_events_list',
        'booking_event_register',
    ];

    /**
     * Node types converted to plain online CTA messages.
     *
     * @var array<int, string>
     */
    public const MESSAGE_ONLINE_TYPES = [
        'template',
        'whatsapp_flow',
        'whatsapp_catalog',
        'listing_inquiry',
        'catalog_search',
        'request_payment',
        'order_status',
        'mpesa_stk_push',
        'pdf',
        'video',
    ];

    /**
     * @var array<int, string>
     */
    public const REPLACE_TYPES = [
        ...self::BOOKING_ONLINE_TYPES,
        ...self::MESSAGE_ONLINE_TYPES,
    ];

    /**
     * @param  array<string, array<string, mixed>>  $templates
     * @return array<string, array<string, mixed>>
     */
    public static function appendOmniVariants(array $templates): array
    {
        $converter = new self;
        $result = [];

        foreach ($templates as $key => $template) {
            if (! isset($template['channel_mode'])) {
                $template['channel_mode'] = 'whatsapp';
            }

            if (! isset($template['channel_badge'])) {
                $template['channel_badge'] = 'WhatsApp';
            }

            $result[$key] = $template;

            if (($template['channel_mode'] ?? '') === 'omni') {
                continue;
            }

            if (! empty($template['skip_omni_variant'])) {
                continue;
            }

            $omniKey = $key.'_omni';
            if (isset($templates[$omniKey]) || isset($result[$omniKey])) {
                continue;
            }

            $result[$omniKey] = $converter->makeOmniTemplate($key, $template);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    public function makeOmniTemplate(string $sourceKey, array $template): array
    {
        $flowData = $this->convertFlowData($template['flow_data'] ?? []);

        $name = (string) ($template['name'] ?? $sourceKey);
        $description = (string) ($template['description'] ?? '');

        return [
            'name' => $name.' (Omni)',
            'description' => trim(
                'WhatsApp + Instagram + Messenger. '
                .$description
                .' Booking, catalogs, forms, and payments are handled via online links instead of WhatsApp-only widgets.'
            ),
            'category' => $template['category'] ?? 'general',
            'video_url' => $template['video_url'] ?? null,
            'setup_hint' => 'Works on WhatsApp, Instagram, and Messenger. Publish, then test keywords on each channel. Booking/browse/shop steps open your online pages (Reminders booking catalog or shop URL).',
            'post_install_checklist' => array_values(array_unique(array_merge(
                [
                    'Confirm public booking / shop pages are live for your company',
                    'Test a keyword on WhatsApp, Instagram, and Messenger',
                    'Verify online booking/catalog links open correctly',
                    'OpenRouter / knowledge base when using AI FAQ',
                    'Publish flow',
                ],
                array_values(array_filter(
                    $template['post_install_checklist'] ?? [],
                    fn ($item) => is_string($item)
                        && ! str_contains(strtolower($item), 'm-pesa')
                        && ! str_contains(strtolower($item), 'whatsapp form')
                        && ! str_contains(strtolower($item), 'catalog id')
                )),
            ))),
            'requires_setup_wizard' => false,
            'exclusive_on_match' => (bool) ($template['exclusive_on_match'] ?? false),
            'channel_mode' => 'omni',
            'channel_badge' => 'Omni',
            'source_whatsapp_template' => $sourceKey,
            'supported_channels' => FlowChannelCompatibility::SUPPORTED_FLOW_CHANNELS,
            'flow_data' => $flowData,
        ];
    }

    /**
     * @param  array<string, mixed>  $flowData
     * @return array<string, mixed>
     */
    public function convertFlowData(array $flowData): array
    {
        $convertedBookingIds = [];
        $nodes = [];

        foreach ($flowData['nodes'] ?? [] as $node) {
            $type = (string) ($node['type'] ?? ($node['data']['type'] ?? ''));
            $converted = $this->convertNode($node);
            $nodes[] = $converted;

            if (in_array($type, self::BOOKING_ONLINE_TYPES, true)) {
                $convertedBookingIds[(string) $node['id']] = true;
            }
        }

        $nodes = array_map(fn (array $node) => $this->polishSharedNodeCopy($node), $nodes);

        $flowData['nodes'] = $nodes;
        $flowData['edges'] = $this->rewriteEdgesForOnlineBooking(
            $flowData['edges'] ?? [],
            $convertedBookingIds
        );
        $flowData['supported_channels'] = FlowChannelCompatibility::SUPPORTED_FLOW_CHANNELS;
        $flowData['meta'] = array_merge($flowData['meta'] ?? [], [
            'channel_mode' => 'omni',
            'supported_channels' => FlowChannelCompatibility::SUPPORTED_FLOW_CHANNELS,
            'online_first' => true,
        ]);

        return $flowData;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function convertNode(array $node): array
    {
        $type = (string) ($node['type'] ?? ($node['data']['type'] ?? ''));

        if (in_array($type, self::BOOKING_ONLINE_TYPES, true)) {
            return $this->toSendBookingLinkNode($node, $type);
        }

        if (in_array($type, self::MESSAGE_ONLINE_TYPES, true)) {
            return $this->toOnlineMessageNode($node, $type);
        }

        // Keep existing send_booking_link nodes; polish copy for omni channels.
        if ($type === 'send_booking_link') {
            return $this->polishSendBookingLinkNode($node);
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function toSendBookingLinkNode(array $node, string $fromType): array
    {
        [$header, $message, $footer] = match ($fromType) {
            'book_appointment' => [
                'Book online',
                "View live services, availability, and prices here:\n{{booking_link}}\n\nComplete your booking on that page, then reply *done* or *menu*.",
                'Need help? Reply *agent*.',
            ],
            'manage_booking' => [
                'Manage online',
                "Cancel or reschedule your appointment online:\n{{booking_link}}\n\nYou will need your booking reference (for example #42) and the name on the booking.\n\nWhen finished, reply *done* or *menu*.",
                'Need a human? Reply *agent*.',
            ],
            'manage_event_registration' => [
                'Manage event online',
                "Cancel your event registration online:\n{{booking_link}}\n\nUse your registration reference and the name on the booking.\n\nReply *done* or *menu* when finished.",
                'Need help? Reply *agent*.',
            ],
            'booking_events_list', 'booking_event_register' => [
                'Events online',
                "Browse and register for events online:\n{{booking_link}}\n\nReply *done* or *menu* when finished.",
                'Need help? Reply *agent*.',
            ],
            default => [
                'Continue online',
                "Continue here:\n{{booking_link}}",
                'Reply *menu* anytime.',
            ],
        };

        $linkType = match ($fromType) {
            'booking_events_list', 'booking_event_register' => 'events',
            'manage_booking' => 'manage',
            'manage_event_registration' => 'manage_events',
            default => 'appointments',
        };

        return [
            'id' => $node['id'],
            'type' => 'send_booking_link',
            'position' => $node['position'] ?? ['x' => 0, 'y' => 0],
            'data' => [
                'label' => $this->defaultLabel($fromType).' (Online)',
                'type' => 'send_booking_link',
                'settings' => [
                    'link_type' => $linkType,
                    'header' => $header,
                    'message' => $message,
                    'footer' => $footer,
                ],
                'omni_replaced_from' => $fromType,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function toOnlineMessageNode(array $node, string $fromType): array
    {
        return [
            'id' => $node['id'],
            'type' => 'message',
            'position' => $node['position'] ?? ['x' => 0, 'y' => 0],
            'data' => [
                'label' => $this->defaultLabel($fromType).' (Online)',
                'type' => 'message',
                'settings' => [
                    'message' => $this->onlineMessageCopy($fromType, $node),
                ],
                'omni_replaced_from' => $fromType,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function polishSendBookingLinkNode(array $node): array
    {
        $settings = $node['data']['settings'] ?? [];
        $settings['header'] = trim((string) ($settings['header'] ?? '')) !== ''
            ? $settings['header']
            : 'Online booking';
        $settings['message'] = trim((string) ($settings['message'] ?? '')) !== ''
            ? $settings['message']
            : "Browse services and available times here:\n{{booking_link}}";

        $footer = (string) ($settings['footer'] ?? '');
        if ($footer === '' || stripos($footer, 'whatsapp') !== false) {
            $settings['footer'] = 'When you are done, reply *menu* or *done*. Need help? Reply *agent*.';
        }

        $node['data']['settings'] = $settings;
        $node['data']['label'] = ($node['data']['label'] ?? 'Online booking').' (Omni)';

        return $node;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function polishSharedNodeCopy(array $node): array
    {
        $type = (string) ($node['type'] ?? '');

        if ($type === 'message') {
            $text = (string) ($node['data']['settings']['message'] ?? '');
            if ($text !== '' && empty($node['data']['omni_replaced_from'])) {
                $node['data']['settings']['message'] = $this->rewriteWelcomeCopy($text);
            }
        }

        if ($type === 'list_message') {
            $settings = $node['data']['settings'] ?? [];
            $sections = $settings['sections'] ?? [];
            foreach ($sections as $sIdx => $section) {
                foreach ($section['rows'] ?? [] as $rIdx => $row) {
                    $title = strtolower((string) ($row['title'] ?? ''));
                    if (str_contains($title, 'book') && ! str_contains($title, 'online')) {
                        $sections[$sIdx]['rows'][$rIdx]['title'] = 'Book online';
                        $sections[$sIdx]['rows'][$rIdx]['description'] = 'Open live services & times';
                    } elseif (str_contains($title, 'reschedule') || str_contains($title, 'cancel')) {
                        $sections[$sIdx]['rows'][$rIdx]['description'] = 'Manage on the online booking page';
                    } elseif (str_contains($title, 'shop') || str_contains($title, 'catalog') || str_contains($title, 'listing')) {
                        $sections[$sIdx]['rows'][$rIdx]['description'] = 'Continue on our online page';
                    }
                }
            }
            $settings['sections'] = $sections;
            $node['data']['settings'] = $settings;
        }

        return $node;
    }

    private function rewriteWelcomeCopy(string $text): string
    {
        $replacements = [
            'Book from our live treatment schedule, change an existing appointment, or ask our spa concierge a question.' => 'Book or manage visits on our online calendar, or ask our spa concierge a question.',
            'Availability and prices shown during booking come directly from our current service calendar.' => 'Tap the online link we send to see live availability and prices, then finish booking there.',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * Map legacy booking handles onto send_booking_link success/error outputs.
     *
     * @param  array<int, array<string, mixed>>  $edges
     * @param  array<string, bool>  $convertedBookingIds
     * @return array<int, array<string, mixed>>
     */
    private function rewriteEdgesForOnlineBooking(array $edges, array $convertedBookingIds): array
    {
        if ($convertedBookingIds === []) {
            return $edges;
        }

        $successHandles = ['success', 'rescheduled', 'cancelled', 'selected', 'onFlowCompleted'];
        $errorHandles = ['error', 'unavailable', 'not_found', 'empty', 'onAbandoned', 'else'];

        foreach ($edges as $index => $edge) {
            $source = (string) ($edge['source'] ?? '');
            if (! isset($convertedBookingIds[$source])) {
                continue;
            }

            $handle = (string) ($edge['sourceHandle'] ?? '');
            if (in_array($handle, $successHandles, true)) {
                $edges[$index]['sourceHandle'] = 'success';
            } elseif (in_array($handle, $errorHandles, true) || $handle === '') {
                $edges[$index]['sourceHandle'] = 'error';
            } else {
                // Unknown handle → prefer success so the online path continues.
                $edges[$index]['sourceHandle'] = 'success';
            }
        }

        return $edges;
    }

    private function defaultLabel(string $type): string
    {
        return match ($type) {
            'whatsapp_catalog', 'listing_inquiry', 'catalog_search' => 'Browse online',
            'whatsapp_flow' => 'Continue online',
            'book_appointment' => 'Book online',
            'booking_events_list', 'booking_event_register' => 'Events online',
            'send_booking_link' => 'Online booking link',
            'manage_booking', 'manage_event_registration' => 'Manage online',
            'request_payment', 'mpesa_stk_push', 'order_status' => 'Pay / track online',
            'template' => 'Announcement',
            'pdf', 'video' => 'Shared media',
            default => 'Continue',
        };
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function onlineMessageCopy(string $type, array $node): string
    {
        $settings = $node['data']['settings'] ?? [];

        return match ($type) {
            'whatsapp_catalog', 'listing_inquiry', 'catalog_search' => "Browse our full catalog online and complete checkout there.\n\nReply *agent* if you need the shop link, or *menu* to go back.",
            'whatsapp_flow' => "Please complete this step on our online form/page.\n\nReply *agent* if you need the link, or send the details here in one message.",
            'request_payment', 'mpesa_stk_push' => "Complete payment on the secure online checkout page.\n\nReply *agent* if you need the payment link resent.",
            'order_status' => "Track your order online with your order reference, or reply with the reference and we will look it up.\n\nReply *agent* for live support.",
            'template' => (string) ($settings['message'] ?? 'Thanks for messaging us — how can we help today?'),
            'pdf' => 'I can share that document as a link. Reply *agent* if you need it sent to you.',
            'video' => 'I can share that video as a link. Reply *agent* if you need help finding it.',
            default => 'Continue online, or reply *agent* for help.',
        };
    }
}
