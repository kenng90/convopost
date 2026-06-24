<?php

/**
 * Curated flow templates for onboarding.
 *
 * Node types must match the flow builder export schema (see docs/flow_8_ai-draft-flow_*.json).
 */
$flowTemplates = [

    'spa_wellness_booking' => [
        'name' => 'Spa & Wellness Booking',
        'description' => 'Full appointment intake: service menu, date/time, guest name, team routing, and optional couples deposit.',
        'category' => 'services',
        'video_url' => null,
        'setup_hint' => 'Set the Bookings group, journey stage (e.g. Registered), and M-Pesa deposit amount on the couples package node.',
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
                            ['id' => 'kw2', 'value' => 'spa', 'matchType' => 'contains'],
                            ['id' => 'kw3', 'value' => 'appointment', 'matchType' => 'contains'],
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
                            'message' => "Welcome to our spa! 🌿\n\nChoose a service below and we'll collect your preferred date and time.\n\nHours: Mon–Sat 9am–7pm | Sun 10am–5pm",
                        ],
                    ],
                ],
                [
                    'id' => 'list_message-1',
                    'type' => 'list_message',
                    'position' => ['x' => 760, 'y' => 120],
                    'data' => [
                        'label' => 'Service menu',
                        'type' => 'list_message',
                        'settings' => [
                            'header' => 'Our services',
                            'body' => 'Select the treatment you would like to book.',
                            'footer' => 'Reply anytime to restart.',
                            'buttonText' => 'View services',
                            'sections' => [
                                [
                                    'id' => 'section1',
                                    'title' => 'Treatments',
                                    'rows' => [
                                        ['id' => 'row1', 'title' => 'Swedish Massage', 'description' => '60 min · KES 4,500'],
                                        ['id' => 'row2', 'title' => 'Deep Cleansing Facial', 'description' => '45 min · KES 3,800'],
                                        ['id' => 'row3', 'title' => 'Manicure & Pedicure', 'description' => '90 min · KES 2,500'],
                                        ['id' => 'row4', 'title' => 'Couples Spa Package', 'description' => '2 hrs · deposit required'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'message-2',
                    'type' => 'message',
                    'position' => ['x' => 1140, 'y' => 520],
                    'data' => [
                        'label' => 'Deposit notice',
                        'type' => 'message',
                        'settings' => [
                            'message' => "Our Couples Spa Package requires a 50% deposit to hold your slot.\n\nWe will send an M-Pesa prompt now. After payment, share your preferred date and time.",
                        ],
                    ],
                ],
                [
                    'id' => 'mpesa_stk_push-1',
                    'type' => 'mpesa_stk_push',
                    'position' => ['x' => 1520, 'y' => 520],
                    'data' => [
                        'label' => 'Couples deposit',
                        'type' => 'mpesa_stk_push',
                        'settings' => [
                            'mpesa' => [
                                'amount' => '5000',
                                'accountReference' => 'SPA-DEPOSIT',
                                'transactionDesc' => 'Spa deposit',
                                'responseVar' => 'spa_deposit_result',
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'message-3',
                    'type' => 'message',
                    'position' => ['x' => 1900, 'y' => 620],
                    'data' => [
                        'label' => 'Payment failed',
                        'type' => 'message',
                        'settings' => [
                            'message' => "We couldn't complete the M-Pesa request. Reply *book* to try again or type *agent* for assistance.",
                        ],
                    ],
                ],
                [
                    'id' => 'question-1',
                    'type' => 'question',
                    'position' => ['x' => 1140, 'y' => 200],
                    'data' => [
                        'label' => 'Preferred date',
                        'type' => 'question',
                        'settings' => [
                            'question' => 'What date would you like to visit? (e.g. Friday 28 June)',
                            'variableName' => 'booking_date',
                        ],
                    ],
                ],
                [
                    'id' => 'question-2',
                    'type' => 'question',
                    'position' => ['x' => 1520, 'y' => 200],
                    'data' => [
                        'label' => 'Preferred time',
                        'type' => 'question',
                        'settings' => [
                            'question' => 'What time works best for you? (e.g. 2:30 PM)',
                            'variableName' => 'booking_time',
                        ],
                    ],
                ],
                [
                    'id' => 'question-3',
                    'type' => 'question',
                    'position' => ['x' => 1900, 'y' => 200],
                    'data' => [
                        'label' => 'Guest name',
                        'type' => 'question',
                        'settings' => [
                            'question' => 'Please share the full name for the booking.',
                            'variableName' => 'guest_name',
                        ],
                    ],
                ],
                [
                    'id' => 'message-4',
                    'type' => 'message',
                    'position' => ['x' => 2280, 'y' => 200],
                    'data' => [
                        'label' => 'Booking received',
                        'type' => 'message',
                        'settings' => [
                            'message' => "Thank you {{guest_name}}! ✅\n\nWe received your request for {{booking_date}} at {{booking_time}}.\n\nOur bookings team will confirm within 2 hours.",
                        ],
                    ],
                ],
                [
                    'id' => 'assign_group-1',
                    'type' => 'assign_group',
                    'position' => ['x' => 2660, 'y' => 200],
                    'data' => [
                        'label' => 'Assign to Bookings team',
                        'type' => 'assign_group',
                        'settings' => [
                            'groupId' => '1',
                            'action' => 'add',
                        ],
                    ],
                ],
                [
                    'id' => 'assign_journey_stage-1',
                    'type' => 'assign_journey_stage',
                    'position' => ['x' => 3040, 'y' => 200],
                    'data' => [
                        'label' => 'Move to Registered',
                        'type' => 'assign_journey_stage',
                        'settings' => [
                            'journeyId' => '1',
                            'stageId' => '1',
                        ],
                    ],
                ],
                [
                    'id' => 'message-5',
                    'type' => 'message',
                    'position' => ['x' => 3420, 'y' => 200],
                    'data' => [
                        'label' => 'Goodbye',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'We look forward to seeing you! Reply *book* anytime to schedule another visit.',
                        ],
                    ],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 3800, 'y' => 320],
                    'data' => ['label' => 'End', 'type' => 'end'],
                ],
            ],
            'edges' => [
                ['id' => 'e-kw1', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'kw1'],
                ['id' => 'e-kw2', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'kw2'],
                ['id' => 'e-kw3', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'kw3'],
                ['id' => 'e-welcome-list', 'source' => 'message-1', 'target' => 'list_message-1'],
                ['id' => 'e-list-massage', 'source' => 'list_message-1', 'target' => 'question-1', 'sourceHandle' => 'section1-row1'],
                ['id' => 'e-list-facial', 'source' => 'list_message-1', 'target' => 'question-1', 'sourceHandle' => 'section1-row2'],
                ['id' => 'e-list-manicure', 'source' => 'list_message-1', 'target' => 'question-1', 'sourceHandle' => 'section1-row3'],
                ['id' => 'e-list-couples', 'source' => 'list_message-1', 'target' => 'message-2', 'sourceHandle' => 'section1-row4'],
                ['id' => 'e-deposit-mpesa', 'source' => 'message-2', 'target' => 'mpesa_stk_push-1'],
                ['id' => 'e-mpesa-success', 'source' => 'mpesa_stk_push-1', 'target' => 'question-1', 'sourceHandle' => 'mpesa-success'],
                ['id' => 'e-mpesa-failed', 'source' => 'mpesa_stk_push-1', 'target' => 'message-3', 'sourceHandle' => 'mpesa-failed'],
                ['id' => 'e-fail-end', 'source' => 'message-3', 'target' => 'end-1'],
                ['id' => 'e-q1-q2', 'source' => 'question-1', 'target' => 'question-2'],
                ['id' => 'e-q2-q3', 'source' => 'question-2', 'target' => 'question-3'],
                ['id' => 'e-q3-confirm', 'source' => 'question-3', 'target' => 'message-4'],
                ['id' => 'e-confirm-group', 'source' => 'message-4', 'target' => 'assign_group-1'],
                ['id' => 'e-group-journey', 'source' => 'assign_group-1', 'target' => 'assign_journey_stage-1'],
                ['id' => 'e-journey-goodbye', 'source' => 'assign_journey_stage-1', 'target' => 'message-5'],
                ['id' => 'e-goodbye-end', 'source' => 'message-5', 'target' => 'end-1'],
            ],
        ],
    ],

    'whatsapp_shop_checkout' => [
        'name' => 'WhatsApp Shop — Catalog & Pay',
        'description' => 'Browse catalog, checkout with M-Pesa, route to fulfillment, or escalate to sales / AI FAQ.',
        'category' => 'commerce',
        'video_url' => null,
        'setup_hint' => 'Set your catalog ID, M-Pesa credentials, Fulfillment group, Sales agent, and journey Paid stage.',
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
                            ['id' => 'kw1', 'value' => 'shop', 'matchType' => 'contains'],
                            ['id' => 'kw2', 'value' => 'buy', 'matchType' => 'contains'],
                            ['id' => 'kw3', 'value' => 'catalog', 'matchType' => 'contains'],
                        ],
                    ],
                ],
                [
                    'id' => 'message-1',
                    'type' => 'message',
                    'position' => ['x' => 380, 'y' => 200],
                    'data' => [
                        'label' => 'Shop intro',
                        'type' => 'message',
                        'settings' => [
                            'message' => "Welcome to our shop! 🛍️\n\nBrowse the catalog below, then choose an option to checkout, ask a question, or speak with sales.",
                        ],
                    ],
                ],
                [
                    'id' => 'whatsapp_catalog-1',
                    'type' => 'whatsapp_catalog',
                    'position' => ['x' => 760, 'y' => 200],
                    'data' => [
                        'label' => 'Send Catalog Link',
                        'type' => 'whatsapp_catalog',
                        'settings' => [
                            'catalogId' => '1',
                            'header' => 'Browse our products',
                            'displayMode' => 'link',
                        ],
                    ],
                ],
                [
                    'id' => 'quick_replies-1',
                    'type' => 'quick_replies',
                    'position' => ['x' => 1140, 'y' => 200],
                    'data' => [
                        'label' => 'Next step',
                        'type' => 'quick_replies',
                        'settings' => [
                            'header' => 'How can we help?',
                            'body' => 'Select an option to continue.',
                            'footer' => 'Reply *shop* to start over.',
                            'activeButtons' => 3,
                            'button1' => 'Checkout',
                            'button2' => 'Ask a question',
                            'button3' => 'Talk to sales',
                        ],
                    ],
                ],
                [
                    'id' => 'question-1',
                    'type' => 'question',
                    'position' => ['x' => 1520, 'y' => 80],
                    'data' => [
                        'label' => 'Delivery address',
                        'type' => 'question',
                        'settings' => [
                            'question' => 'Please share your delivery address or pickup preference.',
                            'variableName' => 'delivery_address',
                        ],
                    ],
                ],
                [
                    'id' => 'question-2',
                    'type' => 'question',
                    'position' => ['x' => 1900, 'y' => 80],
                    'data' => [
                        'label' => 'Order notes',
                        'type' => 'question',
                        'settings' => [
                            'question' => 'Any order notes? (size, colour, quantity, etc.)',
                            'variableName' => 'order_notes',
                        ],
                    ],
                ],
                [
                    'id' => 'message-2',
                    'type' => 'message',
                    'position' => ['x' => 2280, 'y' => 80],
                    'data' => [
                        'label' => 'Order summary',
                        'type' => 'message',
                        'settings' => [
                            'message' => "Order summary 📦\n\nDelivery: {{delivery_address}}\nNotes: {{order_notes}}\n\nWe will send an M-Pesa payment request for the total. Adjust the amount on the payment node before publishing.",
                        ],
                    ],
                ],
                [
                    'id' => 'mpesa_stk_push-1',
                    'type' => 'mpesa_stk_push',
                    'position' => ['x' => 2660, 'y' => 80],
                    'data' => [
                        'label' => 'Collect payment',
                        'type' => 'mpesa_stk_push',
                        'settings' => [
                            'mpesa' => [
                                'amount' => '1500',
                                'accountReference' => 'SHOP-ORDER',
                                'transactionDesc' => 'Shop order',
                                'responseVar' => 'shop_payment_result',
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'assign_journey_stage-1',
                    'type' => 'assign_journey_stage',
                    'position' => ['x' => 3040, 'y' => 80],
                    'data' => [
                        'label' => 'Mark Paid',
                        'type' => 'assign_journey_stage',
                        'settings' => [
                            'journeyId' => '1',
                            'stageId' => '1',
                        ],
                    ],
                ],
                [
                    'id' => 'assign_group-1',
                    'type' => 'assign_group',
                    'position' => ['x' => 3420, 'y' => 80],
                    'data' => [
                        'label' => 'Fulfillment team',
                        'type' => 'assign_group',
                        'settings' => [
                            'groupId' => '1',
                            'action' => 'add',
                        ],
                    ],
                ],
                [
                    'id' => 'message-3',
                    'type' => 'message',
                    'position' => ['x' => 3800, 'y' => 80],
                    'data' => [
                        'label' => 'Payment thanks',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'Payment received! ✅ Our fulfillment team will confirm dispatch shortly.',
                        ],
                    ],
                ],
                [
                    'id' => 'message-4',
                    'type' => 'message',
                    'position' => ['x' => 2660, 'y' => 280],
                    'data' => [
                        'label' => 'Payment failed',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'Payment was not completed. Reply *shop* to try again or choose *Talk to sales* for help.',
                        ],
                    ],
                ],
                [
                    'id' => 'openai-1',
                    'type' => 'openai',
                    'position' => ['x' => 1520, 'y' => 320],
                    'data' => [
                        'label' => 'Shop FAQ bot',
                        'type' => 'openai',
                        'settings' => [
                            'llm' => [
                                'model' => 'openai/gpt-4o-mini',
                                'systemPrompt' => 'You are a helpful shop assistant on WhatsApp. Answer product, shipping, and return questions concisely. If unsure, suggest the customer speak with sales.',
                                'prompt' => '{{contact_last_message}}',
                                'temperature' => 0.7,
                                'maxTokens' => 500,
                                'variableName' => 'shop_faq_reply',
                                'enableVectorSearch' => true,
                                'vectorSearchLimit' => 5,
                                'similarityThreshold' => 0.3,
                                'intentions' => [],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'quick_replies-2',
                    'type' => 'quick_replies',
                    'position' => ['x' => 1900, 'y' => 320],
                    'data' => [
                        'label' => 'FAQ follow-up',
                        'type' => 'quick_replies',
                        'settings' => [
                            'header' => 'Did that help?',
                            'body' => 'Let us know if you need anything else.',
                            'footer' => null,
                            'activeButtons' => 2,
                            'button1' => 'Yes, thanks',
                            'button2' => 'Need a human',
                        ],
                    ],
                ],
                [
                    'id' => 'message-5',
                    'type' => 'message',
                    'position' => ['x' => 2280, 'y' => 280],
                    'data' => [
                        'label' => 'FAQ resolved',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'Glad we could help! Reply *shop* anytime to browse again.',
                        ],
                    ],
                ],
                [
                    'id' => 'assign_agent-1',
                    'type' => 'assign_agent',
                    'position' => ['x' => 2280, 'y' => 480],
                    'data' => [
                        'label' => 'Assign sales agent',
                        'type' => 'assign_agent',
                        'settings' => [
                            'agentId' => 'none',
                        ],
                    ],
                ],
                [
                    'id' => 'assign_group-2',
                    'type' => 'assign_group',
                    'position' => ['x' => 2660, 'y' => 480],
                    'data' => [
                        'label' => 'Sales team',
                        'type' => 'assign_group',
                        'settings' => [
                            'groupId' => '1',
                            'action' => 'add',
                        ],
                    ],
                ],
                [
                    'id' => 'message-6',
                    'type' => 'message',
                    'position' => ['x' => 3040, 'y' => 480],
                    'data' => [
                        'label' => 'Sales handoff',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'A member of our sales team will reply shortly. Thank you for your patience!',
                        ],
                    ],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 4180, 'y' => 320],
                    'data' => ['label' => 'End', 'type' => 'end'],
                ],
            ],
            'edges' => [
                ['id' => 'e-kw1', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'kw1'],
                ['id' => 'e-kw2', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'kw2'],
                ['id' => 'e-kw3', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'kw3'],
                ['id' => 'e-intro-catalog', 'source' => 'message-1', 'target' => 'whatsapp_catalog-1'],
                ['id' => 'e-catalog-menu', 'source' => 'whatsapp_catalog-1', 'target' => 'quick_replies-1', 'sourceHandle' => 'onProductSelected'],
                ['id' => 'e-menu-checkout', 'source' => 'quick_replies-1', 'target' => 'question-1', 'sourceHandle' => 'button-1'],
                ['id' => 'e-menu-faq', 'source' => 'quick_replies-1', 'target' => 'openai-1', 'sourceHandle' => 'button-2'],
                ['id' => 'e-menu-sales', 'source' => 'quick_replies-1', 'target' => 'assign_agent-1', 'sourceHandle' => 'button-3'],
                ['id' => 'e-addr-notes', 'source' => 'question-1', 'target' => 'question-2'],
                ['id' => 'e-notes-summary', 'source' => 'question-2', 'target' => 'message-2'],
                ['id' => 'e-summary-mpesa', 'source' => 'message-2', 'target' => 'mpesa_stk_push-1'],
                ['id' => 'e-mpesa-success', 'source' => 'mpesa_stk_push-1', 'target' => 'assign_journey_stage-1', 'sourceHandle' => 'mpesa-success'],
                ['id' => 'e-mpesa-failed', 'source' => 'mpesa_stk_push-1', 'target' => 'message-4', 'sourceHandle' => 'mpesa-failed'],
                ['id' => 'e-paid-group', 'source' => 'assign_journey_stage-1', 'target' => 'assign_group-1'],
                ['id' => 'e-group-thanks', 'source' => 'assign_group-1', 'target' => 'message-3'],
                ['id' => 'e-thanks-end', 'source' => 'message-3', 'target' => 'end-1'],
                ['id' => 'e-fail-end', 'source' => 'message-4', 'target' => 'end-1'],
                ['id' => 'e-faq-followup', 'source' => 'openai-1', 'target' => 'quick_replies-2'],
                ['id' => 'e-faq-yes', 'source' => 'quick_replies-2', 'target' => 'message-5', 'sourceHandle' => 'button-1'],
                ['id' => 'e-faq-human', 'source' => 'quick_replies-2', 'target' => 'assign_agent-1', 'sourceHandle' => 'button-2'],
                ['id' => 'e-resolved-end', 'source' => 'message-5', 'target' => 'end-1'],
                ['id' => 'e-agent-group', 'source' => 'assign_agent-1', 'target' => 'assign_group-2'],
                ['id' => 'e-group-handoff', 'source' => 'assign_group-2', 'target' => 'message-6'],
                ['id' => 'e-handoff-end', 'source' => 'message-6', 'target' => 'end-1'],
            ],
        ],
    ],

    'lead_intake_routing' => [
        'name' => 'Lead Intake & Team Routing',
        'description' => 'Qualify leads by service type, collect details, and route to sales, intake, or support teams.',
        'category' => 'sales',
        'video_url' => null,
        'setup_hint' => 'Configure groups (Sales, Intake, Accounts, Support), assign agents on the Enterprise path, and link journey stages.',
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
                            ['id' => 'kw1', 'value' => 'quote', 'matchType' => 'contains'],
                            ['id' => 'kw2', 'value' => 'interested', 'matchType' => 'contains'],
                            ['id' => 'kw3', 'value' => 'consult', 'matchType' => 'contains'],
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
                            'message' => "Thanks for reaching out! 👋\n\nWe typically respond within one business day. How can we help you today?",
                        ],
                    ],
                ],
                [
                    'id' => 'quick_replies-1',
                    'type' => 'quick_replies',
                    'position' => ['x' => 760, 'y' => 200],
                    'data' => [
                        'label' => 'Intent menu',
                        'type' => 'quick_replies',
                        'settings' => [
                            'header' => 'Choose an option',
                            'body' => 'Select the option that best describes your request.',
                            'footer' => null,
                            'activeButtons' => 3,
                            'button1' => 'Get a quote',
                            'button2' => 'Existing client',
                            'button3' => 'General question',
                        ],
                    ],
                ],
                [
                    'id' => 'list_message-1',
                    'type' => 'list_message',
                    'position' => ['x' => 1140, 'y' => 80],
                    'data' => [
                        'label' => 'Service lines',
                        'type' => 'list_message',
                        'settings' => [
                            'header' => 'Our services',
                            'body' => 'Which service are you interested in?',
                            'footer' => null,
                            'buttonText' => 'Select service',
                            'sections' => [
                                [
                                    'id' => 'section1',
                                    'title' => 'Services',
                                    'rows' => [
                                        ['id' => 'row1', 'title' => 'Consultation', 'description' => 'Discovery call or site visit'],
                                        ['id' => 'row2', 'title' => 'Installation', 'description' => 'New setup or deployment'],
                                        ['id' => 'row3', 'title' => 'Maintenance', 'description' => 'Ongoing support contract'],
                                        ['id' => 'row4', 'title' => 'Enterprise project', 'description' => 'Large scope · dedicated rep'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'question-1',
                    'type' => 'question',
                    'position' => ['x' => 1520, 'y' => 80],
                    'data' => [
                        'label' => 'Full name',
                        'type' => 'question',
                        'settings' => [
                            'question' => 'What is your full name?',
                            'variableName' => 'lead_name',
                        ],
                    ],
                ],
                [
                    'id' => 'question-2',
                    'type' => 'question',
                    'position' => ['x' => 1900, 'y' => 80],
                    'data' => [
                        'label' => 'Project need',
                        'type' => 'question',
                        'settings' => [
                            'question' => 'Briefly describe what you need help with.',
                            'variableName' => 'lead_need',
                        ],
                    ],
                ],
                [
                    'id' => 'question-3',
                    'type' => 'question',
                    'position' => ['x' => 2280, 'y' => 80],
                    'data' => [
                        'label' => 'Location',
                        'type' => 'question',
                        'settings' => [
                            'question' => 'What city or area are you located in?',
                            'variableName' => 'lead_location',
                        ],
                    ],
                ],
                [
                    'id' => 'datastore-1',
                    'type' => 'datastore',
                    'position' => ['x' => 2660, 'y' => 80],
                    'data' => [
                        'label' => 'Tag lead profile',
                        'type' => 'datastore',
                        'settings' => [
                            'dataStore' => [
                                'name' => 'lead_profile',
                                'type' => 'database',
                                'connectionDetails' => [],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'assign_group-1',
                    'type' => 'assign_group',
                    'position' => ['x' => 3040, 'y' => 80],
                    'data' => [
                        'label' => 'Intake team',
                        'type' => 'assign_group',
                        'settings' => [
                            'groupId' => '1',
                            'action' => 'add',
                        ],
                    ],
                ],
                [
                    'id' => 'assign_agent-1',
                    'type' => 'assign_agent',
                    'position' => ['x' => 3420, 'y' => 80],
                    'data' => [
                        'label' => 'Assign sales agent',
                        'type' => 'assign_agent',
                        'settings' => [
                            'agentId' => 'none',
                        ],
                    ],
                ],
                [
                    'id' => 'assign_journey_stage-1',
                    'type' => 'assign_journey_stage',
                    'position' => ['x' => 3800, 'y' => 80],
                    'data' => [
                        'label' => 'New Lead stage',
                        'type' => 'assign_journey_stage',
                        'settings' => [
                            'journeyId' => '1',
                            'stageId' => '1',
                        ],
                    ],
                ],
                [
                    'id' => 'message-2',
                    'type' => 'message',
                    'position' => ['x' => 4180, 'y' => 80],
                    'data' => [
                        'label' => 'Quote confirmation',
                        'type' => 'message',
                        'settings' => [
                            'message' => "Thank you {{lead_name}}! ✅\n\nWe received your request and will send a quote within 24 hours.\n\nLocation: {{lead_location}}",
                        ],
                    ],
                ],
                [
                    'id' => 'question-4',
                    'type' => 'question',
                    'position' => ['x' => 1140, 'y' => 360],
                    'data' => [
                        'label' => 'Account reference',
                        'type' => 'question',
                        'settings' => [
                            'question' => 'Please share your account or invoice reference number.',
                            'variableName' => 'account_reference',
                        ],
                    ],
                ],
                [
                    'id' => 'assign_group-3',
                    'type' => 'assign_group',
                    'position' => ['x' => 1520, 'y' => 360],
                    'data' => [
                        'label' => 'Accounts team',
                        'type' => 'assign_group',
                        'settings' => [
                            'groupId' => '1',
                            'action' => 'add',
                        ],
                    ],
                ],
                [
                    'id' => 'message-3',
                    'type' => 'message',
                    'position' => ['x' => 1900, 'y' => 360],
                    'data' => [
                        'label' => 'Accounts ack',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'Thank you! Our accounts team will look up reference {{account_reference}} and reply shortly.',
                        ],
                    ],
                ],
                [
                    'id' => 'openai-1',
                    'type' => 'openai',
                    'position' => ['x' => 1140, 'y' => 560],
                    'data' => [
                        'label' => 'General FAQ',
                        'type' => 'openai',
                        'settings' => [
                            'llm' => [
                                'model' => 'openai/gpt-4o-mini',
                                'systemPrompt' => 'You are a professional services assistant. Answer general questions about the business clearly and concisely.',
                                'prompt' => '{{contact_last_message}}',
                                'temperature' => 0.7,
                                'maxTokens' => 500,
                                'variableName' => 'general_faq_reply',
                                'enableVectorSearch' => true,
                                'vectorSearchLimit' => 5,
                                'similarityThreshold' => 0.3,
                                'intentions' => [],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'quick_replies-2',
                    'type' => 'quick_replies',
                    'position' => ['x' => 1520, 'y' => 560],
                    'data' => [
                        'label' => 'FAQ outcome',
                        'type' => 'quick_replies',
                        'settings' => [
                            'header' => 'Did that answer your question?',
                            'body' => 'Choose an option below.',
                            'footer' => null,
                            'activeButtons' => 2,
                            'button1' => 'Yes, thanks',
                            'button2' => 'Speak to someone',
                        ],
                    ],
                ],
                [
                    'id' => 'message-4',
                    'type' => 'message',
                    'position' => ['x' => 1900, 'y' => 520],
                    'data' => [
                        'label' => 'FAQ thanks',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'Happy to help! Reply *quote* anytime to request a formal proposal.',
                        ],
                    ],
                ],
                [
                    'id' => 'assign_agent-2',
                    'type' => 'assign_agent',
                    'position' => ['x' => 1900, 'y' => 640],
                    'data' => [
                        'label' => 'Support agent',
                        'type' => 'assign_agent',
                        'settings' => [
                            'agentId' => 'none',
                        ],
                    ],
                ],
                [
                    'id' => 'assign_group-4',
                    'type' => 'assign_group',
                    'position' => ['x' => 2280, 'y' => 640],
                    'data' => [
                        'label' => 'Support team',
                        'type' => 'assign_group',
                        'settings' => [
                            'groupId' => '1',
                            'action' => 'add',
                        ],
                    ],
                ],
                [
                    'id' => 'message-5',
                    'type' => 'message',
                    'position' => ['x' => 2660, 'y' => 640],
                    'data' => [
                        'label' => 'Support handoff',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'An agent will follow up with you shortly. Thank you for your patience!',
                        ],
                    ],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 4560, 'y' => 320],
                    'data' => ['label' => 'End', 'type' => 'end'],
                ],
            ],
            'edges' => [
                ['id' => 'e-kw1', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'kw1'],
                ['id' => 'e-kw2', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'kw2'],
                ['id' => 'e-kw3', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'kw3'],
                ['id' => 'e-welcome-menu', 'source' => 'message-1', 'target' => 'quick_replies-1'],
                ['id' => 'e-menu-quote', 'source' => 'quick_replies-1', 'target' => 'list_message-1', 'sourceHandle' => 'button-1'],
                ['id' => 'e-menu-existing', 'source' => 'quick_replies-1', 'target' => 'question-4', 'sourceHandle' => 'button-2'],
                ['id' => 'e-menu-general', 'source' => 'quick_replies-1', 'target' => 'openai-1', 'sourceHandle' => 'button-3'],
                ['id' => 'e-list-consult', 'source' => 'list_message-1', 'target' => 'question-1', 'sourceHandle' => 'section1-row1'],
                ['id' => 'e-list-install', 'source' => 'list_message-1', 'target' => 'question-1', 'sourceHandle' => 'section1-row2'],
                ['id' => 'e-list-maint', 'source' => 'list_message-1', 'target' => 'question-1', 'sourceHandle' => 'section1-row3'],
                ['id' => 'e-list-enterprise', 'source' => 'list_message-1', 'target' => 'question-1', 'sourceHandle' => 'section1-row4'],
                ['id' => 'e-q1-q2', 'source' => 'question-1', 'target' => 'question-2'],
                ['id' => 'e-q2-q3', 'source' => 'question-2', 'target' => 'question-3'],
                ['id' => 'e-q3-store', 'source' => 'question-3', 'target' => 'datastore-1'],
                ['id' => 'e-store-intake', 'source' => 'datastore-1', 'target' => 'assign_group-1'],
                ['id' => 'e-intake-agent', 'source' => 'assign_group-1', 'target' => 'assign_agent-1'],
                ['id' => 'e-agent-journey', 'source' => 'assign_agent-1', 'target' => 'assign_journey_stage-1'],
                ['id' => 'e-journey-confirm', 'source' => 'assign_journey_stage-1', 'target' => 'message-2'],
                ['id' => 'e-confirm-end', 'source' => 'message-2', 'target' => 'end-1'],
                ['id' => 'e-ref-accounts', 'source' => 'question-4', 'target' => 'assign_group-3'],
                ['id' => 'e-accounts-ack', 'source' => 'assign_group-3', 'target' => 'message-3'],
                ['id' => 'e-ack-end', 'source' => 'message-3', 'target' => 'end-1'],
                ['id' => 'e-faq-followup', 'source' => 'openai-1', 'target' => 'quick_replies-2'],
                ['id' => 'e-faq-yes', 'source' => 'quick_replies-2', 'target' => 'message-4', 'sourceHandle' => 'button-1'],
                ['id' => 'e-faq-human', 'source' => 'quick_replies-2', 'target' => 'assign_agent-2', 'sourceHandle' => 'button-2'],
                ['id' => 'e-thanks-end', 'source' => 'message-4', 'target' => 'end-1'],
                ['id' => 'e-agent-support', 'source' => 'assign_agent-2', 'target' => 'assign_group-4'],
                ['id' => 'e-support-handoff', 'source' => 'assign_group-4', 'target' => 'message-5'],
                ['id' => 'e-handoff-end', 'source' => 'message-5', 'target' => 'end-1'],
            ],
        ],
    ],

    'support_ai_escalation' => [
        'name' => 'Support Desk — AI Triage & Escalation',
        'description' => 'Category-based support with LLM first response, then agent and journey escalation.',
        'category' => 'support',
        'video_url' => null,
        'setup_hint' => 'Add OpenRouter API key, train FAQ docs on the flow, and set Support group, agent, and journey In Progress stage.',
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
                            ['id' => 'kw2', 'value' => 'support', 'matchType' => 'contains'],
                            ['id' => 'kw3', 'value' => 'issue', 'matchType' => 'contains'],
                        ],
                    ],
                ],
                [
                    'id' => 'message-1',
                    'type' => 'message',
                    'position' => ['x' => 380, 'y' => 200],
                    'data' => [
                        'label' => 'Support greeting',
                        'type' => 'message',
                        'settings' => [
                            'message' => "Support team here. 🛟\n\nPick a category below. Type *agent* anytime to skip to a human.",
                        ],
                    ],
                ],
                [
                    'id' => 'list_message-1',
                    'type' => 'list_message',
                    'position' => ['x' => 760, 'y' => 200],
                    'data' => [
                        'label' => 'Issue category',
                        'type' => 'list_message',
                        'settings' => [
                            'header' => 'How can we help?',
                            'body' => 'Select the category that best matches your issue.',
                            'footer' => 'We aim to reply within 4 business hours.',
                            'buttonText' => 'Choose category',
                            'sections' => [
                                [
                                    'id' => 'section1',
                                    'title' => 'Categories',
                                    'rows' => [
                                        ['id' => 'row1', 'title' => 'Orders & delivery', 'description' => 'Tracking, delays, wrong item'],
                                        ['id' => 'row2', 'title' => 'Billing & payments', 'description' => 'Invoices, refunds, M-Pesa'],
                                        ['id' => 'row3', 'title' => 'Technical issue', 'description' => 'App, login, or product problem'],
                                        ['id' => 'row4', 'title' => 'Account & profile', 'description' => 'Updates, access, credentials'],
                                        ['id' => 'row5', 'title' => 'Something else', 'description' => 'General enquiry'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'openai-1',
                    'type' => 'openai',
                    'position' => ['x' => 1140, 'y' => 200],
                    'data' => [
                        'label' => 'Support AI',
                        'type' => 'openai',
                        'settings' => [
                            'llm' => [
                                'model' => 'openai/gpt-4o-mini',
                                'systemPrompt' => 'You are a customer support agent on WhatsApp. Use the knowledge base when available. Be empathetic, concise, and actionable. Escalate politely if the issue needs a human.',
                                'prompt' => '{{contact_last_message}}',
                                'temperature' => 0.6,
                                'maxTokens' => 600,
                                'variableName' => 'support_ai_reply',
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
                    'position' => ['x' => 1520, 'y' => 200],
                    'data' => [
                        'label' => 'Resolution check',
                        'type' => 'quick_replies',
                        'settings' => [
                            'header' => 'Did we solve it?',
                            'body' => 'Let us know how you would like to proceed.',
                            'footer' => null,
                            'activeButtons' => 3,
                            'button1' => 'Resolved',
                            'button2' => 'Still need help',
                            'button3' => 'Talk to agent',
                        ],
                    ],
                ],
                [
                    'id' => 'message-2',
                    'type' => 'message',
                    'position' => ['x' => 1900, 'y' => 120],
                    'data' => [
                        'label' => 'Case resolved',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'Glad we could help! Reply *help* anytime if you need further assistance.',
                        ],
                    ],
                ],
                [
                    'id' => 'question-1',
                    'type' => 'question',
                    'position' => ['x' => 1900, 'y' => 280],
                    'data' => [
                        'label' => 'Issue details',
                        'type' => 'question',
                        'settings' => [
                            'question' => 'Please describe the issue in a bit more detail so we can assist you.',
                            'variableName' => 'support_issue_detail',
                        ],
                    ],
                ],
                [
                    'id' => 'counter-1',
                    'type' => 'counter',
                    'position' => ['x' => 2280, 'y' => 280],
                    'data' => [
                        'label' => 'Limit AI loops',
                        'type' => 'counter',
                        'settings' => [
                            'counter' => [
                                'maxExecutions' => 2,
                                'period' => 'all_time',
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'openai-2',
                    'type' => 'openai',
                    'position' => ['x' => 2660, 'y' => 200],
                    'data' => [
                        'label' => 'Follow-up AI',
                        'type' => 'openai',
                        'settings' => [
                            'llm' => [
                                'model' => 'openai/gpt-4o-mini',
                                'systemPrompt' => 'The customer still needs help. Review their detailed issue and provide one more helpful response before human escalation.',
                                'prompt' => 'Issue details: {{support_issue_detail}}',
                                'temperature' => 0.6,
                                'maxTokens' => 500,
                                'variableName' => 'support_followup_reply',
                                'enableVectorSearch' => true,
                                'vectorSearchLimit' => 3,
                                'similarityThreshold' => 0.3,
                                'intentions' => [],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'message-3',
                    'type' => 'message',
                    'position' => ['x' => 2660, 'y' => 400],
                    'data' => [
                        'label' => 'Escalating',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'Connecting you with a support agent now. Please hold — average wait under 10 minutes.',
                        ],
                    ],
                ],
                [
                    'id' => 'assign_group-1',
                    'type' => 'assign_group',
                    'position' => ['x' => 3040, 'y' => 320],
                    'data' => [
                        'label' => 'Support queue',
                        'type' => 'assign_group',
                        'settings' => [
                            'groupId' => '1',
                            'action' => 'add',
                        ],
                    ],
                ],
                [
                    'id' => 'assign_agent-1',
                    'type' => 'assign_agent',
                    'position' => ['x' => 3420, 'y' => 320],
                    'data' => [
                        'label' => 'Assign agent',
                        'type' => 'assign_agent',
                        'settings' => [
                            'agentId' => 'none',
                        ],
                    ],
                ],
                [
                    'id' => 'assign_journey_stage-1',
                    'type' => 'assign_journey_stage',
                    'position' => ['x' => 3800, 'y' => 320],
                    'data' => [
                        'label' => 'In Progress',
                        'type' => 'assign_journey_stage',
                        'settings' => [
                            'journeyId' => '1',
                            'stageId' => '1',
                        ],
                    ],
                ],
                [
                    'id' => 'message-4',
                    'type' => 'message',
                    'position' => ['x' => 4180, 'y' => 320],
                    'data' => [
                        'label' => 'Agent SLA',
                        'type' => 'message',
                        'settings' => [
                            'message' => "You're in the queue. Ticket reference: {{support_issue_detail}}\n\nOur team will reply here on WhatsApp.",
                        ],
                    ],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 4560, 'y' => 320],
                    'data' => ['label' => 'End', 'type' => 'end'],
                ],
            ],
            'edges' => [
                ['id' => 'e-kw1', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'kw1'],
                ['id' => 'e-kw2', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'kw2'],
                ['id' => 'e-kw3', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'kw3'],
                ['id' => 'e-greet-list', 'source' => 'message-1', 'target' => 'list_message-1'],
                ['id' => 'e-cat1-ai', 'source' => 'list_message-1', 'target' => 'openai-1', 'sourceHandle' => 'section1-row1'],
                ['id' => 'e-cat2-ai', 'source' => 'list_message-1', 'target' => 'openai-1', 'sourceHandle' => 'section1-row2'],
                ['id' => 'e-cat3-ai', 'source' => 'list_message-1', 'target' => 'openai-1', 'sourceHandle' => 'section1-row3'],
                ['id' => 'e-cat4-ai', 'source' => 'list_message-1', 'target' => 'openai-1', 'sourceHandle' => 'section1-row4'],
                ['id' => 'e-cat5-ai', 'source' => 'list_message-1', 'target' => 'openai-1', 'sourceHandle' => 'section1-row5'],
                ['id' => 'e-ai-menu', 'source' => 'openai-1', 'target' => 'quick_replies-1'],
                ['id' => 'e-resolved', 'source' => 'quick_replies-1', 'target' => 'message-2', 'sourceHandle' => 'button-1'],
                ['id' => 'e-still-help', 'source' => 'quick_replies-1', 'target' => 'question-1', 'sourceHandle' => 'button-2'],
                ['id' => 'e-agent-now', 'source' => 'quick_replies-1', 'target' => 'assign_group-1', 'sourceHandle' => 'button-3'],
                ['id' => 'e-resolved-end', 'source' => 'message-2', 'target' => 'end-1'],
                ['id' => 'e-detail-counter', 'source' => 'question-1', 'target' => 'counter-1'],
                ['id' => 'e-counter-true', 'source' => 'counter-1', 'target' => 'openai-2', 'sourceHandle' => 'true'],
                ['id' => 'e-counter-false', 'source' => 'counter-1', 'target' => 'message-3', 'sourceHandle' => 'false'],
                ['id' => 'e-followup-escalate', 'source' => 'openai-2', 'target' => 'assign_group-1'],
                ['id' => 'e-escalate-msg', 'source' => 'message-3', 'target' => 'assign_group-1'],
                ['id' => 'e-agent-path-group', 'source' => 'assign_group-1', 'target' => 'assign_agent-1'],
                ['id' => 'e-group-journey', 'source' => 'assign_agent-1', 'target' => 'assign_journey_stage-1'],
                ['id' => 'e-journey-sla', 'source' => 'assign_journey_stage-1', 'target' => 'message-4'],
                ['id' => 'e-sla-end', 'source' => 'message-4', 'target' => 'end-1'],
            ],
        ],
    ],

];

return array_merge($flowTemplates, require __DIR__.'/flow-templates-industry.php');
