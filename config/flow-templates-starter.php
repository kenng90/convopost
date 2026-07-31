<?php

use App\Services\Flowmaker\FaqConversationLoop;

/**
 * Starter templates aligned with draft/publish, LLM auto-send, listing booking, and voice AI.
 */
return [

    'ai_faq_minimal' => [
        'name' => 'AI FAQ — Minimal',
        'description' => 'Keyword-triggered conversational AI FAQ loop with optional human escalation. Ideal first bot.',
        'category' => 'general',
        'video_url' => null,
        'setup_hint' => 'Add OpenRouter API key, train knowledge base on this flow, then Save draft → Publish. Set Support group ID before going live.',
        'post_install_checklist' => [
            'OpenRouter / AI key configured',
            'Knowledge base trained on FAQs',
            'Support group ID set on escalation node',
            'Click Publish in flow editor',
        ],
        'flow_data' => FaqConversationLoop::mergeInto([
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
                            ['id' => 'kw4', 'value' => 'agent', 'matchType' => 'exact'],
                            ['id' => 'kw5', 'value' => 'human', 'matchType' => 'exact'],
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
                            'message' => 'Glad we could help! Reply *help* anytime, or *agent* to speak with our team.',
                        ],
                    ],
                ],
                [
                    'id' => 'quick_replies-resolved',
                    'type' => 'quick_replies',
                    'position' => ['x' => 1330, 'y' => 120],
                    'data' => [
                        'label' => 'After thanks',
                        'type' => 'quick_replies',
                        'settings' => [
                            'header' => 'Anything else?',
                            'body' => 'Ask again, talk to someone, or finish.',
                            'footer' => 'Reply *help* anytime.',
                            'activeButtons' => 3,
                            'button1' => 'Ask again',
                            'button2' => 'Talk to agent',
                            'button3' => 'Done',
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
                ['id' => 'e-kw1', 'source' => 'keyword_trigger-1', 'target' => 'minimal-faq-question-initial', 'sourceHandle' => 'keyword-kw1'],
                ['id' => 'e-kw2', 'source' => 'keyword_trigger-1', 'target' => 'minimal-faq-question-initial', 'sourceHandle' => 'keyword-kw2'],
                ['id' => 'e-kw3', 'source' => 'keyword_trigger-1', 'target' => 'minimal-faq-question-initial', 'sourceHandle' => 'keyword-kw3'],
                ['id' => 'e-kw4', 'source' => 'keyword_trigger-1', 'target' => 'assign_group-1', 'sourceHandle' => 'keyword-kw4'],
                ['id' => 'e-kw5', 'source' => 'keyword_trigger-1', 'target' => 'assign_group-1', 'sourceHandle' => 'keyword-kw5'],
                ['id' => 'e-thanks-next', 'source' => 'message-1', 'target' => 'quick_replies-resolved'],
                ['id' => 'e-ask-again', 'source' => 'quick_replies-resolved', 'target' => 'minimal-faq-question-initial', 'sourceHandle' => 'button-1'],
                ['id' => 'e-resolved-agent', 'source' => 'quick_replies-resolved', 'target' => 'assign_group-1', 'sourceHandle' => 'button-2'],
                ['id' => 'e-resolved-done', 'source' => 'quick_replies-resolved', 'target' => 'end-1', 'sourceHandle' => 'button-3'],
                ['id' => 'e-handoff-end', 'source' => 'message-2', 'target' => 'end-1'],
                ['id' => 'e-group-msg', 'source' => 'assign_group-1', 'target' => 'message-2'],
            ],
        ], [
            'idPrefix' => 'minimal-faq',
            'basePosition' => ['x' => 380, 'y' => 200],
            'questionInitial' => 'How can I help you today?',
            'questionFollowup' => 'Anything else? Reply *done* when finished or *human* / *agent* to speak with our team.',
            'systemPrompt' => 'You are a helpful FAQ assistant on WhatsApp. Answer from the knowledge base when possible. Be concise. Never repeat the customer question — always provide a helpful answer. If you cannot help, suggest speaking with a human.',
            'llmVariableName' => 'faq_reply',
            'llmLabel' => 'FAQ assistant',
            'counterMax' => 10,
            'doneTarget' => 'message-1',
            'humanTarget' => 'assign_group-1',
        ]),
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
        'exclusive_on_match' => true,
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
                            ['id' => 'kw3', 'value' => 'help', 'matchType' => 'exact'],
                            ['id' => 'kw4', 'value' => 'agent', 'matchType' => 'exact'],
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
                            'message' => "Welcome! Browse our services below and book directly on WhatsApp.\n\nReply *agent* anytime to speak with our team.",
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
                            'footer' => 'Reply *agent* for a human.',
                            'displayMode' => 'interactive_list',
                            'autoResumeFlow' => true,
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
                            'message' => "Thanks {{listing_booking_customer_name}}!\n\nWe received your booking request for *{{listing_booking_item_title}}* on {{listing_booking_preferred_datetime}}.\n\nOur team will confirm shortly.",
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
                    'id' => 'assign_group-agent',
                    'type' => 'assign_group',
                    'position' => ['x' => 1330, 'y' => 360],
                    'data' => [
                        'label' => 'Agent queue',
                        'type' => 'assign_group',
                        'settings' => ['groupId' => 'none', 'action' => 'add'],
                    ],
                ],
                [
                    'id' => 'message-handoff',
                    'type' => 'message',
                    'position' => ['x' => 1520, 'y' => 360],
                    'data' => [
                        'label' => 'Agent handoff',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'A team member will reply shortly. Thank you for your patience.',
                        ],
                    ],
                ],
                [
                    'id' => 'quick_replies-after-book',
                    'type' => 'quick_replies',
                    'position' => ['x' => 1710, 'y' => 200],
                    'data' => [
                        'label' => 'After booking',
                        'type' => 'quick_replies',
                        'settings' => [
                            'header' => 'Anything else?',
                            'body' => 'Browse more services, talk to our team, or finish.',
                            'footer' => 'Reply *book* anytime.',
                            'activeButtons' => 3,
                            'button1' => 'Browse again',
                            'button2' => 'Talk to agent',
                            'button3' => 'Done',
                        ],
                    ],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 2090, 'y' => 200],
                    'data' => ['label' => 'End', 'type' => 'end'],
                ],
            ],
            'edges' => [
                ['id' => 'e-kw1', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'keyword-kw1'],
                ['id' => 'e-kw2', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'keyword-kw2'],
                ['id' => 'e-kw3', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'keyword-kw3'],
                ['id' => 'e-kw4', 'source' => 'keyword_trigger-1', 'target' => 'assign_group-agent', 'sourceHandle' => 'keyword-kw4'],
                ['id' => 'e-welcome-listing', 'source' => 'message-1', 'target' => 'listing_inquiry-1'],
                ['id' => 'e-listing-confirm', 'source' => 'listing_inquiry-1', 'target' => 'message-2', 'sourceHandle' => 'onListingInquiry'],
                ['id' => 'e-confirm-group', 'source' => 'message-2', 'target' => 'assign_group-1'],
                ['id' => 'e-group-next', 'source' => 'assign_group-1', 'target' => 'quick_replies-after-book'],
                ['id' => 'e-browse-again', 'source' => 'quick_replies-after-book', 'target' => 'listing_inquiry-1', 'sourceHandle' => 'button-1'],
                ['id' => 'e-after-agent', 'source' => 'quick_replies-after-book', 'target' => 'assign_group-agent', 'sourceHandle' => 'button-2'],
                ['id' => 'e-after-done', 'source' => 'quick_replies-after-book', 'target' => 'end-1', 'sourceHandle' => 'button-3'],
                ['id' => 'e-agent-handoff', 'source' => 'assign_group-agent', 'target' => 'message-handoff'],
                ['id' => 'e-handoff-end', 'source' => 'message-handoff', 'target' => 'end-1'],
            ],
        ],
    ],

    'catalog_listings_showcase' => [
        'name' => 'Catalog — Listings Showcase',
        'description' => 'Non-commerce catalog in listing mode: properties, classes, or packages with inquiry capture and AI FAQ loop.',
        'category' => 'commerce',
        'video_url' => null,
        'setup_hint' => 'Run setup (or Edit) to bind a listing-mode catalog and sales group. Train optional AI FAQ, then Publish.',
        'post_install_checklist' => [
            'Listing-mode catalog ID',
            'Sales group configured',
            'OpenRouter key for FAQ (optional)',
            'Publish when ready',
        ],
        'requires_setup_wizard' => true,
        'exclusive_on_match' => true,
        'flow_data' => FaqConversationLoop::mergeInto([
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
                            ['id' => 'kw3', 'value' => 'help', 'matchType' => 'exact'],
                            ['id' => 'kw4', 'value' => 'agent', 'matchType' => 'exact'],
                            ['id' => 'kw5', 'value' => 'human', 'matchType' => 'exact'],
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
                            'footer' => 'Reply *agent* for sales help.',
                            'displayMode' => 'interactive_list',
                            'autoResumeFlow' => true,
                            'completionType' => 'inquiry',
                            'bookingVariablePrefix' => 'listing_inquiry',
                            'requirePreferredDateTime' => false,
                            'bookingBackend' => 'whatsapp_only',
                        ],
                    ],
                ],
                [
                    'id' => 'message-inquiry-received',
                    'type' => 'message',
                    'position' => ['x' => 950, 'y' => 80],
                    'data' => [
                        'label' => 'Inquiry received',
                        'type' => 'message',
                        'settings' => [
                            'message' => "Thanks! We noted your interest in *{{listing_inquiry_item_title}}*.\n\nAsk a question below, or reply *agent* for sales.",
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
                    'id' => 'message-handoff',
                    'type' => 'message',
                    'position' => ['x' => 1330, 'y' => 200],
                    'data' => [
                        'label' => 'Sales handoff',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'A sales specialist will reply here shortly. Thank you for your interest.',
                        ],
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
                ['id' => 'e-kw3', 'source' => 'keyword_trigger-1', 'target' => 'listing_inquiry-1', 'sourceHandle' => 'keyword-kw3'],
                ['id' => 'e-kw4', 'source' => 'keyword_trigger-1', 'target' => 'assign_group-1', 'sourceHandle' => 'keyword-kw4'],
                ['id' => 'e-kw5', 'source' => 'keyword_trigger-1', 'target' => 'assign_group-1', 'sourceHandle' => 'keyword-kw5'],
                ['id' => 'e-listing-ack', 'source' => 'listing_inquiry-1', 'target' => 'message-inquiry-received', 'sourceHandle' => 'onListingInquiry'],
                ['id' => 'e-ack-faq', 'source' => 'message-inquiry-received', 'target' => 'listing-faq-question-initial'],
                ['id' => 'e-group-handoff', 'source' => 'assign_group-1', 'target' => 'message-handoff'],
                ['id' => 'e-handoff-end', 'source' => 'message-handoff', 'target' => 'end-1'],
            ],
        ], [
            'idPrefix' => 'listing-faq',
            'basePosition' => ['x' => 760, 'y' => 200],
            'questionInitial' => 'What would you like to know about {{listing_inquiry_item_title}} or our other listings?',
            'questionFollowup' => 'More questions? Reply *done* when finished, *browse* to see listings again, or *agent* / *human* for sales.',
            'systemPrompt' => 'You help customers understand listings. Use catalog knowledge when available. Never repeat the customer question — always provide a helpful answer.',
            'prompt' => "Listing context: {{listing_inquiry_item_title}}\n\nCustomer question:\n{{contact_last_message}}",
            'llmVariableName' => 'listing_faq_reply',
            'llmLabel' => 'Listing FAQ',
            'counterMax' => 6,
            'keywordExits' => [
                ['id' => 'cond-browse', 'keyword' => 'browse', 'target' => 'listing_inquiry-1'],
                ['id' => 'cond-listings', 'keyword' => 'listings', 'target' => 'listing_inquiry-1'],
                ['id' => 'cond-help', 'keyword' => 'help', 'target' => 'listing_inquiry-1'],
            ],
            'doneTarget' => 'end-1',
            'humanTarget' => 'assign_group-1',
        ]),
    ],

    'whatsapp_voice_ai_agent' => [
        'name' => 'WhatsApp Voice AI Agent',
        'description' => 'Voice-call AI instructions and knowledge base. Assign as your Voice AI flow to power WhatsApp phone calls — not an always-on chat bot.',
        'category' => 'ai',
        'video_url' => null,
        'setup_hint' => 'Assign this flow under WhatsApp Calling → AI voice settings. Train knowledge base documents on this flow, attach catalogs for phone orders, review the Voice instructions node, then Publish. Use AI FAQ or Support templates for chat.',
        'post_install_checklist' => [
            'Set as Voice AI flow in WhatsApp Calling → AI voice settings',
            'OpenAI Realtime API key configured for live calls',
            'Knowledge base documents uploaded to this flow',
            'Product catalogs attached in Voice AI settings (optional)',
            'Voice instructions system prompt reviewed',
            'Separate chat bot installed if you need always-on WhatsApp messaging',
            'Publish flow',
        ],
        'flow_data' => [
            'nodes' => [
                [
                    'id' => 'incomingMessage-1',
                    'type' => 'incomingMessage',
                    'position' => ['x' => 0, 'y' => 280],
                    'data' => [
                        'label' => 'On chat message',
                        'type' => 'incomingMessage',
                        'settings' => [],
                    ],
                ],
                [
                    'id' => 'keyword_trigger-1',
                    'type' => 'keyword_trigger',
                    'position' => ['x' => 0, 'y' => 80],
                    'data' => [
                        'label' => 'Handoff keywords',
                        'type' => 'keyword_trigger',
                        'keywords' => [
                            ['id' => 'kw1', 'value' => 'agent', 'matchType' => 'exact'],
                            ['id' => 'kw2', 'value' => 'human', 'matchType' => 'exact'],
                            ['id' => 'kw3', 'value' => 'call', 'matchType' => 'exact'],
                            ['id' => 'kw4', 'value' => 'voice', 'matchType' => 'exact'],
                        ],
                    ],
                ],
                [
                    'id' => 'openai-voice-instructions',
                    'type' => 'openai',
                    'position' => ['x' => 760, 'y' => 80],
                    'data' => [
                        'label' => 'Voice call instructions',
                        'type' => 'openai',
                        'settings' => [
                            'llm' => [
                                'model' => 'openai/gpt-4o-mini',
                                'systemPrompt' => 'You are the AI voice agent for this business on WhatsApp phone calls. Speak in natural, short sentences suited for audio — never long paragraphs. Use the knowledge base and runtime capability brief. Help callers with products, appointments, event registration, and general questions. When taking a catalog order, confirm the exact product name and price aloud; a WhatsApp invoice may be sent after the call. Offer a warm transfer to a human when unsure or when the caller asks. Never invent inventory, prices, hours, or policies. Do not read long lists unless asked. For emergencies, tell the caller to contact local emergency services immediately.',
                                'prompt' => '{{contact_last_message}}',
                                'temperature' => 0.5,
                                'maxTokens' => 400,
                                'variableName' => 'voice_ai_instructions',
                                'autoSendMessage' => false,
                                'enableVectorSearch' => true,
                                'vectorSearchLimit' => 5,
                                'similarityThreshold' => 0.3,
                                'intentions' => [],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'message-chat-notice',
                    'type' => 'message',
                    'position' => ['x' => 380, 'y' => 280],
                    'data' => [
                        'label' => 'Chat not voice AI',
                        'type' => 'message',
                        'settings' => [
                            'message' => "This number uses *Voice AI* for WhatsApp *phone calls*, not always-on chat.\n\nCall us on WhatsApp for the AI assistant, reply *agent* to reach our team, or install a separate FAQ/support bot for messaging.",
                        ],
                    ],
                ],
                [
                    'id' => 'message-call-hint',
                    'type' => 'message',
                    'position' => ['x' => 760, 'y' => 240],
                    'data' => [
                        'label' => 'Call hint',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'Start a WhatsApp voice call to speak with our AI assistant. Reply *agent* if you prefer a team member on chat.',
                        ],
                    ],
                ],
                [
                    'id' => 'quick_replies-chat',
                    'type' => 'quick_replies',
                    'position' => ['x' => 760, 'y' => 360],
                    'data' => [
                        'label' => 'Chat options',
                        'type' => 'quick_replies',
                        'settings' => [
                            'header' => 'How can we help?',
                            'body' => 'Choose an option below.',
                            'footer' => 'Reply *agent* anytime.',
                            'activeButtons' => 2,
                            'button1' => 'Call us',
                            'button2' => 'Talk to agent',
                            'button3' => '',
                        ],
                    ],
                ],
                [
                    'id' => 'assign_agent-1',
                    'type' => 'assign_agent',
                    'position' => ['x' => 380, 'y' => 80],
                    'data' => [
                        'label' => 'Assign agent',
                        'type' => 'assign_agent',
                        'settings' => ['agentId' => 'none'],
                    ],
                ],
                [
                    'id' => 'assign_group-1',
                    'type' => 'assign_group',
                    'position' => ['x' => 760, 'y' => 80],
                    'data' => [
                        'label' => 'Support team',
                        'type' => 'assign_group',
                        'settings' => ['groupId' => 'none', 'action' => 'add'],
                    ],
                ],
                [
                    'id' => 'message-handoff',
                    'type' => 'message',
                    'position' => ['x' => 1140, 'y' => 80],
                    'data' => [
                        'label' => 'Agent handoff',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'Connecting you with a team member on chat. For the AI voice assistant, start a WhatsApp call instead.',
                        ],
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
                ['id' => 'e-incoming-notice', 'source' => 'incomingMessage-1', 'target' => 'message-chat-notice'],
                ['id' => 'e-notice-options', 'source' => 'message-chat-notice', 'target' => 'quick_replies-chat'],
                ['id' => 'e-chat-call', 'source' => 'quick_replies-chat', 'target' => 'message-call-hint', 'sourceHandle' => 'button-1'],
                ['id' => 'e-chat-agent', 'source' => 'quick_replies-chat', 'target' => 'assign_agent-1', 'sourceHandle' => 'button-2'],
                ['id' => 'e-call-hint-end', 'source' => 'message-call-hint', 'target' => 'end-1'],
                ['id' => 'e-kw1-agent', 'source' => 'keyword_trigger-1', 'target' => 'assign_agent-1', 'sourceHandle' => 'keyword-kw1'],
                ['id' => 'e-kw2-agent', 'source' => 'keyword_trigger-1', 'target' => 'assign_agent-1', 'sourceHandle' => 'keyword-kw2'],
                ['id' => 'e-kw3-call', 'source' => 'keyword_trigger-1', 'target' => 'message-call-hint', 'sourceHandle' => 'keyword-kw3'],
                ['id' => 'e-kw4-instructions', 'source' => 'keyword_trigger-1', 'target' => 'openai-voice-instructions', 'sourceHandle' => 'keyword-kw4'],
                ['id' => 'e-agent-group', 'source' => 'assign_agent-1', 'target' => 'assign_group-1'],
                ['id' => 'e-group-handoff', 'source' => 'assign_group-1', 'target' => 'message-handoff'],
                ['id' => 'e-handoff-end', 'source' => 'message-handoff', 'target' => 'end-1'],
                ['id' => 'e-voice-instructions-end', 'source' => 'openai-voice-instructions', 'target' => 'end-1'],
            ],
        ],
    ],

    // 'whatsapp_form_collect' => [
    //     'name' => 'WhatsApp Form — Collect responses',
    //     'description' => 'Install from a Live WhatsApp Form: keyword → form → thank you. Works with any form schema.',
    //     'category' => 'services',
    //     'requires_setup_wizard' => true,
    //     'exclusive_on_match' => true,
    //     'setup_hint' => 'Prefer Automate on the WhatsApp Forms list. Requires a Live form ID.',
    //     'post_install_checklist' => ['Live WhatsApp Form', 'Review keyword trigger', 'Publish automation'],
    //     'flow_data' => ['nodes' => [], 'edges' => []],
    // ],

    // 'whatsapp_form_lead' => [
    //     'name' => 'WhatsApp Form — Lead recipe',
    //     'description' => 'Install from a Live WhatsApp Form: form → team → thanks. Use Automate on the form list, or pass whatsapp_flow_id.',
    //     'category' => 'services',
    //     'requires_setup_wizard' => true,
    //     'exclusive_on_match' => true,
    //     'setup_hint' => 'Prefer Automate on the WhatsApp Forms list. Requires a Live form ID.',
    //     'post_install_checklist' => ['Live WhatsApp Form', 'Leads group', 'Publish automation'],
    //     'flow_data' => ['nodes' => [], 'edges' => []],
    // ],

    // 'whatsapp_form_book' => [
    //     'name' => 'WhatsApp Form — Book recipe',
    //     'description' => 'Install from a Live WhatsApp Form: form → book appointment → team.',
    //     'category' => 'services',
    //     'requires_setup_wizard' => true,
    //     'exclusive_on_match' => true,
    //     'setup_hint' => 'Prefer Automate on the WhatsApp Forms list. Requires a Live form ID.',
    //     'post_install_checklist' => ['Live WhatsApp Form', 'Bookable service', 'Bookings group', 'Publish'],
    //     'flow_data' => ['nodes' => [], 'edges' => []],
    // ],

    // 'whatsapp_form_checkout' => [
    //     'name' => 'WhatsApp Form — Checkout recipe',
    //     'description' => 'Install from a Live WhatsApp Form: form → payment → order status → fulfillment.',
    //     'category' => 'commerce',
    //     'requires_setup_wizard' => true,
    //     'exclusive_on_match' => true,
    //     'setup_hint' => 'Prefer Automate on the WhatsApp Forms list. Requires a Live form ID.',
    //     'post_install_checklist' => ['Live WhatsApp Form', 'Payment provider', 'Fulfillment group', 'Publish'],
    //     'flow_data' => ['nodes' => [], 'edges' => []],
    // ],

];
