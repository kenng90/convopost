<?php

/**
 * Starter templates aligned with draft/publish, LLM auto-send, listing booking, and voice AI.
 */
return [

    'ai_faq_minimal' => [
        'name' => 'AI FAQ — Minimal',
        'description' => 'Keyword-triggered AI answers with optional human escalation. Ideal first bot.',
        'category' => 'general',
        'video_url' => null,
        'setup_hint' => 'Add OpenRouter API key, train knowledge base on this flow, then Save draft → Publish. Set Support group ID before going live.',
        'post_install_checklist' => [
            'OpenRouter / AI key configured',
            'Knowledge base trained on FAQs',
            'Support group ID set on escalation node',
            'Click Publish in flow editor',
        ],
        'flow_data' => [
            'nodes' => [
                [
                    'id' => 'keyword_trigger-1',
                    'type' => 'keyword_trigger',
                    'position' => ['x' => 0, 'y' => 200],
                    'data' => [
                        'label' => 'On Keyword',
                        'type' => 'keyword_trigger',
                        'keywords' => [
                            ['id' => 'kw1', 'value' => 'help', 'matchType' => 'contains'],
                            ['id' => 'kw2', 'value' => 'faq', 'matchType' => 'contains'],
                            ['id' => 'kw3', 'value' => 'info', 'matchType' => 'contains'],
                        ],
                    ],
                ],
                [
                    'id' => 'openai-1',
                    'type' => 'openai',
                    'position' => ['x' => 380, 'y' => 200],
                    'data' => [
                        'label' => 'FAQ assistant',
                        'type' => 'openai',
                        'settings' => [
                            'llm' => [
                                'model' => 'openai/gpt-4o-mini',
                                'systemPrompt' => 'You are a helpful FAQ assistant on WhatsApp. Answer from the knowledge base when possible. Be concise. If you cannot help, suggest speaking with a human.',
                                'prompt' => '{{contact_last_message}}',
                                'temperature' => 0.5,
                                'maxTokens' => 500,
                                'variableName' => 'faq_reply',
                                'autoSendMessage' => true,
                                'enableVectorSearch' => true,
                                'vectorSearchLimit' => 5,
                                'similarityThreshold' => 0.3,
                                'intentions' => [],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'quick_replies-1',
                    'type' => 'quick_replies',
                    'position' => ['x' => 760, 'y' => 200],
                    'data' => [
                        'label' => 'Was this helpful?',
                        'type' => 'quick_replies',
                        'settings' => [
                            'header' => 'Did that help?',
                            'body' => 'Let us know if you need more assistance.',
                            'footer' => null,
                            'activeButtons' => 2,
                            'button1' => 'Yes, thanks',
                            'button2' => 'Talk to human',
                        ],
                    ],
                ],
                [
                    'id' => 'message-1',
                    'type' => 'message',
                    'position' => ['x' => 1140, 'y' => 120],
                    'data' => [
                        'label' => 'Thanks',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'Glad we could help! Reply *help* anytime.',
                        ],
                    ],
                ],
                [
                    'id' => 'assign_group-1',
                    'type' => 'assign_group',
                    'position' => ['x' => 1140, 'y' => 320],
                    'data' => [
                        'label' => 'Support team',
                        'type' => 'assign_group',
                        'settings' => ['groupId' => 'none', 'action' => 'add'],
                    ],
                ],
                [
                    'id' => 'message-2',
                    'type' => 'message',
                    'position' => ['x' => 1520, 'y' => 320],
                    'data' => [
                        'label' => 'Handoff',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'A team member will reply shortly. Thank you for your patience.',
                        ],
                    ],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 1900, 'y' => 200],
                    'data' => ['label' => 'End', 'type' => 'end'],
                ],
            ],
            'edges' => [
                ['id' => 'e-kw1', 'source' => 'keyword_trigger-1', 'target' => 'openai-1', 'sourceHandle' => 'keyword-kw1'],
                ['id' => 'e-kw2', 'source' => 'keyword_trigger-1', 'target' => 'openai-1', 'sourceHandle' => 'keyword-kw2'],
                ['id' => 'e-kw3', 'source' => 'keyword_trigger-1', 'target' => 'openai-1', 'sourceHandle' => 'keyword-kw3'],
                ['id' => 'e-ai-qr', 'source' => 'openai-1', 'target' => 'quick_replies-1'],
                ['id' => 'e-yes-end', 'source' => 'quick_replies-1', 'target' => 'message-1', 'sourceHandle' => 'button-1'],
                ['id' => 'e-human-group', 'source' => 'quick_replies-1', 'target' => 'assign_group-1', 'sourceHandle' => 'button-2'],
                ['id' => 'e-thanks-end', 'source' => 'message-1', 'target' => 'end-1'],
                ['id' => 'e-handoff-end', 'source' => 'message-2', 'target' => 'end-1'],
                ['id' => 'e-group-msg', 'source' => 'assign_group-1', 'target' => 'message-2'],
            ],
        ],
    ],

    'services_listing_booking' => [
        'name' => 'Services — Listing & Booking',
        'description' => 'Browse listing-mode catalog, capture booking details on WhatsApp, and route to your team.',
        'category' => 'services',
        'video_url' => null,
        'setup_hint' => 'Create a listing-mode catalog, set its ID on the Listing Inquiry node, configure Reminders optional backend, then Publish.',
        'post_install_checklist' => [
            'Listing-mode catalog created',
            'Catalog ID on Listing Inquiry node',
            'Booking team group ID configured',
            'Flow published',
        ],
        'flow_data' => [
            'nodes' => [
                [
                    'id' => 'keyword_trigger-1',
                    'type' => 'keyword_trigger',
                    'position' => ['x' => 0, 'y' => 200],
                    'data' => [
                        'label' => 'On Keyword',
                        'type' => 'keyword_trigger',
                        'keywords' => [
                            ['id' => 'kw1', 'value' => 'book', 'matchType' => 'contains'],
                            ['id' => 'kw2', 'value' => 'services', 'matchType' => 'contains'],
                        ],
                    ],
                ],
                [
                    'id' => 'message-1',
                    'type' => 'message',
                    'position' => ['x' => 380, 'y' => 200],
                    'data' => [
                        'label' => 'Welcome',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'Welcome! Browse our services below and book directly on WhatsApp.',
                        ],
                    ],
                ],
                [
                    'id' => 'listing_inquiry-1',
                    'type' => 'listing_inquiry',
                    'position' => ['x' => 760, 'y' => 200],
                    'data' => [
                        'label' => 'Service listings',
                        'type' => 'listing_inquiry',
                        'settings' => [
                            'catalogId' => '',
                            'header' => 'Our services',
                            'footer' => 'Tap a listing to book on WhatsApp.',
                            'completionType' => 'booking',
                            'bookingVariablePrefix' => 'listing_booking',
                            'requirePreferredDateTime' => true,
                            'bookingBackend' => 'whatsapp_only',
                        ],
                    ],
                ],
                [
                    'id' => 'message-2',
                    'type' => 'message',
                    'position' => ['x' => 1140, 'y' => 200],
                    'data' => [
                        'label' => 'Booking received',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'Thanks {{listing_booking_customer_name}}! We received your booking request for {{listing_booking_item_title}} on {{listing_booking_preferred_datetime}}.',
                        ],
                    ],
                ],
                [
                    'id' => 'assign_group-1',
                    'type' => 'assign_group',
                    'position' => ['x' => 1520, 'y' => 200],
                    'data' => [
                        'label' => 'Bookings team',
                        'type' => 'assign_group',
                        'settings' => ['groupId' => 'none', 'action' => 'add'],
                    ],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 1900, 'y' => 200],
                    'data' => ['label' => 'End', 'type' => 'end'],
                ],
            ],
            'edges' => [
                ['id' => 'e-kw1', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'keyword-kw1'],
                ['id' => 'e-kw2', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'keyword-kw2'],
                ['id' => 'e-welcome-listing', 'source' => 'message-1', 'target' => 'listing_inquiry-1'],
                ['id' => 'e-listing-confirm', 'source' => 'listing_inquiry-1', 'target' => 'message-2', 'sourceHandle' => 'onListingInquiry'],
                ['id' => 'e-confirm-group', 'source' => 'message-2', 'target' => 'assign_group-1'],
                ['id' => 'e-group-end', 'source' => 'assign_group-1', 'target' => 'end-1'],
            ],
        ],
    ],

    'catalog_listings_showcase' => [
        'name' => 'Catalog — Listings Showcase',
        'description' => 'Non-commerce catalog in listing mode: properties, classes, or packages with inquiry capture.',
        'category' => 'commerce',
        'video_url' => null,
        'setup_hint' => 'Use a listing-mode catalog (not product checkout). Set catalog ID, train AI optional FAQ path, Publish when ready.',
        'post_install_checklist' => [
            'Catalog mode set to Listing',
            'Catalog ID on Listing Inquiry node',
            'Sales group configured',
            'Published live',
        ],
        'flow_data' => [
            'nodes' => [
                [
                    'id' => 'keyword_trigger-1',
                    'type' => 'keyword_trigger',
                    'position' => ['x' => 0, 'y' => 200],
                    'data' => [
                        'label' => 'On Keyword',
                        'type' => 'keyword_trigger',
                        'keywords' => [
                            ['id' => 'kw1', 'value' => 'listings', 'matchType' => 'contains'],
                            ['id' => 'kw2', 'value' => 'browse', 'matchType' => 'contains'],
                        ],
                    ],
                ],
                [
                    'id' => 'listing_inquiry-1',
                    'type' => 'listing_inquiry',
                    'position' => ['x' => 380, 'y' => 200],
                    'data' => [
                        'label' => 'Browse listings',
                        'type' => 'listing_inquiry',
                        'settings' => [
                            'catalogId' => '',
                            'header' => 'Available listings',
                            'footer' => 'Reply with questions after viewing.',
                            'completionType' => 'inquiry',
                            'bookingVariablePrefix' => 'listing_inquiry',
                            'requirePreferredDateTime' => false,
                            'bookingBackend' => 'whatsapp_only',
                        ],
                    ],
                ],
                [
                    'id' => 'openai-1',
                    'type' => 'openai',
                    'position' => ['x' => 760, 'y' => 200],
                    'data' => [
                        'label' => 'Listing FAQ',
                        'type' => 'openai',
                        'settings' => [
                            'llm' => [
                                'model' => 'openai/gpt-4o-mini',
                                'systemPrompt' => 'You help customers understand listings. Use catalog knowledge when available.',
                                'prompt' => '{{contact_last_message}}',
                                'temperature' => 0.6,
                                'maxTokens' => 500,
                                'variableName' => 'listing_faq_reply',
                                'autoSendMessage' => true,
                                'enableVectorSearch' => true,
                                'vectorSearchLimit' => 5,
                                'similarityThreshold' => 0.3,
                                'intentions' => [],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'assign_group-1',
                    'type' => 'assign_group',
                    'position' => ['x' => 1140, 'y' => 200],
                    'data' => [
                        'label' => 'Sales team',
                        'type' => 'assign_group',
                        'settings' => ['groupId' => 'none', 'action' => 'add'],
                    ],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 1520, 'y' => 200],
                    'data' => ['label' => 'End', 'type' => 'end'],
                ],
            ],
            'edges' => [
                ['id' => 'e-kw1', 'source' => 'keyword_trigger-1', 'target' => 'listing_inquiry-1', 'sourceHandle' => 'keyword-kw1'],
                ['id' => 'e-kw2', 'source' => 'keyword_trigger-1', 'target' => 'listing_inquiry-1', 'sourceHandle' => 'keyword-kw2'],
                ['id' => 'e-listing-ai', 'source' => 'listing_inquiry-1', 'target' => 'openai-1', 'sourceHandle' => 'onListingInquiry'],
                ['id' => 'e-ai-group', 'source' => 'openai-1', 'target' => 'assign_group-1'],
                ['id' => 'e-group-end', 'source' => 'assign_group-1', 'target' => 'end-1'],
            ],
        ],
    ],

    'whatsapp_voice_ai_agent' => [
        'name' => 'WhatsApp Voice AI Agent',
        'description' => 'Conversational AI for always-on chat: rate-limited LLM with knowledge base and human escalation.',
        'category' => 'ai',
        'video_url' => null,
        'setup_hint' => 'Assign this flow as your company Voice/AI flow (whatsapp_ai_flow_id). Train knowledge base, set OpenRouter key, configure counter limits, then Publish.',
        'post_install_checklist' => [
            'Set as Voice AI flow in company WhatsApp AI settings',
            'OpenRouter API key added',
            'Knowledge base trained',
            'Counter max executions reviewed',
            'Support agent/group for escalation',
            'Publish flow',
        ],
        'flow_data' => [
            'nodes' => [
                [
                    'id' => 'incomingMessage-1',
                    'type' => 'incomingMessage',
                    'position' => ['x' => 0, 'y' => 240],
                    'data' => [
                        'label' => 'On every message',
                        'type' => 'incomingMessage',
                        'settings' => [],
                    ],
                ],
                [
                    'id' => 'keyword_trigger-1',
                    'type' => 'keyword_trigger',
                    'position' => ['x' => 0, 'y' => 80],
                    'data' => [
                        'label' => 'Voice keywords',
                        'type' => 'keyword_trigger',
                        'keywords' => [
                            ['id' => 'kw1', 'value' => 'agent', 'matchType' => 'contains'],
                            ['id' => 'kw2', 'value' => 'human', 'matchType' => 'contains'],
                        ],
                    ],
                ],
                [
                    'id' => 'counter-1',
                    'type' => 'counter',
                    'position' => ['x' => 380, 'y' => 240],
                    'data' => [
                        'label' => 'Rate limit',
                        'type' => 'counter',
                        'settings' => [
                            'counter' => ['maxExecutions' => 20, 'period' => 'last_30_days'],
                        ],
                    ],
                ],
                [
                    'id' => 'check_pricing-1',
                    'type' => 'check_pricing',
                    'position' => ['x' => 760, 'y' => 240],
                    'data' => [
                        'label' => 'AI credits',
                        'type' => 'check_pricing',
                        'settings' => [
                            'pricing' => ['freeExecutions' => 10],
                        ],
                    ],
                ],
                [
                    'id' => 'openai-1',
                    'type' => 'openai',
                    'position' => ['x' => 1140, 'y' => 240],
                    'data' => [
                        'label' => 'Voice AI',
                        'type' => 'openai',
                        'settings' => [
                            'llm' => [
                                'model' => 'openai/gpt-4o-mini',
                                'systemPrompt' => 'You are the voice of this business on WhatsApp. Be warm, concise, and helpful. Use the knowledge base. Never invent prices or policies. For emergencies or account access issues, tell the user to type *agent*.',
                                'prompt' => '{{contact_last_message}}',
                                'temperature' => 0.6,
                                'maxTokens' => 700,
                                'variableName' => 'voice_ai_reply',
                                'autoSendMessage' => true,
                                'enableVectorSearch' => true,
                                'vectorSearchLimit' => 6,
                                'similarityThreshold' => 0.3,
                                'intentions' => [
                                    ['id' => 'int-sales', 'name' => 'sales', 'description' => 'Pricing, products, purchasing'],
                                    ['id' => 'int-support', 'name' => 'support', 'description' => 'Help, issues, complaints'],
                                    ['id' => 'int-general', 'name' => 'general', 'description' => 'General questions'],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'assign_agent-1',
                    'type' => 'assign_agent',
                    'position' => ['x' => 380, 'y' => 80],
                    'data' => [
                        'label' => 'Human agent',
                        'type' => 'assign_agent',
                        'settings' => ['agentId' => 'none'],
                    ],
                ],
                [
                    'id' => 'message-1',
                    'type' => 'message',
                    'position' => ['x' => 760, 'y' => 80],
                    'data' => [
                        'label' => 'Agent handoff',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'Connecting you with a team member now. Please hold.',
                        ],
                    ],
                ],
                [
                    'id' => 'message-2',
                    'type' => 'message',
                    'position' => ['x' => 1140, 'y' => 480],
                    'data' => [
                        'label' => 'Limit reached',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'You have reached the AI assistant limit for this period. Reply *agent* for human help.',
                        ],
                    ],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 1520, 'y' => 240],
                    'data' => ['label' => 'End', 'type' => 'end'],
                ],
            ],
            'edges' => [
                ['id' => 'e-incoming-counter', 'source' => 'incomingMessage-1', 'target' => 'counter-1'],
                ['id' => 'e-kw1-agent', 'source' => 'keyword_trigger-1', 'target' => 'assign_agent-1', 'sourceHandle' => 'keyword-kw1'],
                ['id' => 'e-kw2-agent', 'source' => 'keyword_trigger-1', 'target' => 'assign_agent-1', 'sourceHandle' => 'keyword-kw2'],
                ['id' => 'e-agent-msg', 'source' => 'assign_agent-1', 'target' => 'message-1'],
                ['id' => 'e-handoff-end', 'source' => 'message-1', 'target' => 'end-1'],
                ['id' => 'e-counter-true', 'source' => 'counter-1', 'target' => 'check_pricing-1', 'sourceHandle' => 'true'],
                ['id' => 'e-counter-false', 'source' => 'counter-1', 'target' => 'message-2', 'sourceHandle' => 'false'],
                ['id' => 'e-pricing-ai', 'source' => 'check_pricing-1', 'target' => 'openai-1', 'sourceHandle' => 'true'],
                ['id' => 'e-pricing-false', 'source' => 'check_pricing-1', 'target' => 'message-2', 'sourceHandle' => 'false'],
                ['id' => 'e-ai-end', 'source' => 'openai-1', 'target' => 'end-1', 'sourceHandle' => 'default'],
                ['id' => 'e-limit-end', 'source' => 'message-2', 'target' => 'end-1'],
            ],
        ],
    ],

];
