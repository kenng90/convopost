<?php

use App\Services\Flowmaker\FaqConversationLoop;

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
        'setup_hint' => 'Before Publish: set Bookings group & journey stage, deposit amount / payment provider on couples package, and Reminders bookable service on Book Appointment node. Save draft → Publish when IDs are set.',
        'post_install_checklist' => [
            'Bookings group & journey stage IDs',
            'Deposit amount & payment provider (couples package)',
            'Reminders service linked on Book Appointment',
            'Publish flow',
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
                            'message' => "Our Couples Spa Package requires a 50% deposit to hold your slot.\n\nWe will send a payment prompt now. After payment, share your preferred date and time.",
                        ],
                    ],
                ],
                [
                    'id' => 'request_payment-1',
                    'type' => 'request_payment',
                    'position' => ['x' => 1520, 'y' => 520],
                    'data' => [
                        'label' => 'Couples deposit',
                        'type' => 'request_payment',
                        'settings' => [
                            'payment' => [
                                'amount' => '5000',
                                'accountReference' => 'SPA-DEPOSIT',
                                'description' => 'Spa deposit',
                                'provider' => 'auto',
                                'email' => '',
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
                            'message' => "We couldn't complete the payment request. Reply *book* to try again or type *agent* for assistance.",
                        ],
                    ],
                ],
                [
                    'id' => 'book_appointment-1',
                    'type' => 'book_appointment',
                    'position' => ['x' => 1140, 'y' => 200],
                    'data' => [
                        'label' => 'Book treatment',
                        'type' => 'book_appointment',
                        'settings' => [
                            'source_name' => 'Spa Treatment',
                            'duration_minutes' => '60',
                            'header' => 'Book your visit',
                            'body' => 'Choose a date and time for your spa appointment.',
                            'footer' => '',
                            'buttonText' => 'Choose slot',
                            'success_message' => 'Thank you! Your {{booking_service}} appointment on {{booking_date}} at {{booking_time}} is confirmed.',
                        ],
                    ],
                ],
                [
                    'id' => 'counter-1',
                    'type' => 'counter',
                    'position' => ['x' => 1520, 'y' => 520],
                    'data' => [
                        'label' => 'Deposit limit',
                        'type' => 'counter',
                        'settings' => [
                            'counter' => ['maxExecutions' => 2, 'period' => 'last_30_days'],
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
                            'message' => "Thank you! ✅\n\nWe received your spa booking request.\n\nOur bookings team will confirm within 2 hours.",
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
                ['id' => 'e-kw1', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'keyword-kw1'],
                ['id' => 'e-kw2', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'keyword-kw2'],
                ['id' => 'e-kw3', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'keyword-kw3'],
                ['id' => 'e-welcome-list', 'source' => 'message-1', 'target' => 'list_message-1'],
                ['id' => 'e-list-massage', 'source' => 'list_message-1', 'target' => 'book_appointment-1', 'sourceHandle' => 'section1-row1'],
                ['id' => 'e-list-facial', 'source' => 'list_message-1', 'target' => 'book_appointment-1', 'sourceHandle' => 'section1-row2'],
                ['id' => 'e-list-manicure', 'source' => 'list_message-1', 'target' => 'book_appointment-1', 'sourceHandle' => 'section1-row3'],
                ['id' => 'e-list-couples', 'source' => 'list_message-1', 'target' => 'message-2', 'sourceHandle' => 'section1-row4'],
                ['id' => 'e-deposit-counter', 'source' => 'message-2', 'target' => 'counter-1'],
                ['id' => 'e-counter-true-pay', 'source' => 'counter-1', 'target' => 'request_payment-1', 'sourceHandle' => 'true'],
                ['id' => 'e-counter-false-end', 'source' => 'counter-1', 'target' => 'end-1', 'sourceHandle' => 'false'],
                ['id' => 'e-pay-success', 'source' => 'request_payment-1', 'target' => 'book_appointment-1', 'sourceHandle' => 'success'],
                ['id' => 'e-pay-failed', 'source' => 'request_payment-1', 'target' => 'message-3', 'sourceHandle' => 'failed'],
                ['id' => 'e-fail-end', 'source' => 'message-3', 'target' => 'end-1'],
                ['id' => 'e-book-confirm', 'source' => 'book_appointment-1', 'target' => 'message-4'],
                ['id' => 'e-confirm-group', 'source' => 'message-4', 'target' => 'assign_group-1'],
                ['id' => 'e-group-journey', 'source' => 'assign_group-1', 'target' => 'assign_journey_stage-1'],
                ['id' => 'e-journey-goodbye', 'source' => 'assign_journey_stage-1', 'target' => 'message-5'],
                ['id' => 'e-goodbye-end', 'source' => 'message-5', 'target' => 'end-1'],
            ],
        ],
    ],

    'whatsapp_shop_checkout' => [
        'name' => 'WhatsApp Shop — Catalog & Pay',
        'description' => 'Browse catalog, checkout with payment, confirm order status, route to fulfillment, or escalate to sales / AI FAQ.',
        'category' => 'commerce',
        'video_url' => null,
        'setup_hint' => 'Run the shop setup wizard to bind your catalog, payment provider, and fulfillment team. Save draft → Publish.',
        'post_install_checklist' => ['Catalog', 'Payment provider', 'Fulfillment group', 'OpenRouter key for FAQ', 'Publish'],
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
                            ['id' => 'kw1', 'value' => 'shop', 'matchType' => 'contains'],
                            ['id' => 'kw2', 'value' => 'buy', 'matchType' => 'contains'],
                            // ['id' => 'kw3', 'value' => 'catalog', 'matchType' => 'contains'],
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
                            'displayMode' => 'interactive_list',
                            'autoResumeFlow' => true,
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
                            'message' => "Order summary 📦\n\nDelivery: {{delivery_address}}\nNotes: {{order_notes}}\n\nWe will send a payment request for the total.",
                        ],
                    ],
                ],
                [
                    'id' => 'request_payment-1',
                    'type' => 'request_payment',
                    'position' => ['x' => 2660, 'y' => 80],
                    'data' => [
                        'label' => 'Collect payment',
                        'type' => 'request_payment',
                        'settings' => [
                            'payment' => [
                                'amount' => '{{catalog_order_total_amount}}',
                                'provider' => 'auto',
                                'accountReference' => 'SHOP-ORDER',
                                'description' => 'Shop order',
                                'responseVar' => 'shop_payment_result',
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'order_status-1',
                    'type' => 'order_status',
                    'position' => ['x' => 3040, 'y' => 80],
                    'data' => [
                        'label' => 'Confirm order',
                        'type' => 'order_status',
                        'settings' => [
                            'status' => 'confirmed',
                            'message' => 'Payment received! ✅ Your order is now *{{order_status}}*. Reference: {{order_reference}}',
                            'journeyId' => 'none',
                            'stageId' => 'none',
                        ],
                    ],
                ],
                [
                    'id' => 'assign_journey_stage-1',
                    'type' => 'assign_journey_stage',
                    'position' => ['x' => 3420, 'y' => 80],
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
                    'position' => ['x' => 3800, 'y' => 80],
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
                    'position' => ['x' => 4180, 'y' => 80],
                    'data' => [
                        'label' => 'Payment thanks',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'Our fulfillment team will confirm dispatch shortly. Reply *shop* anytime to order again.',
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
                ['id' => 'e-kw1', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'keyword-kw1'],
                ['id' => 'e-kw2', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'keyword-kw2'],
                ['id' => 'e-kw3', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'keyword-kw3'],
                ['id' => 'e-intro-catalog', 'source' => 'message-1', 'target' => 'whatsapp_catalog-1'],
                ['id' => 'e-catalog-menu', 'source' => 'whatsapp_catalog-1', 'target' => 'quick_replies-1', 'sourceHandle' => 'onProductSelected'],
                ['id' => 'e-menu-checkout', 'source' => 'quick_replies-1', 'target' => 'question-1', 'sourceHandle' => 'button-1'],
                ['id' => 'e-menu-faq', 'source' => 'quick_replies-1', 'target' => 'shop-faq-message-intro', 'sourceHandle' => 'button-2'],
                ['id' => 'e-menu-sales', 'source' => 'quick_replies-1', 'target' => 'assign_agent-1', 'sourceHandle' => 'button-3'],
                ['id' => 'e-addr-notes', 'source' => 'question-1', 'target' => 'question-2'],
                ['id' => 'e-notes-summary', 'source' => 'question-2', 'target' => 'message-2'],
                ['id' => 'e-summary-pay', 'source' => 'message-2', 'target' => 'request_payment-1'],
                ['id' => 'e-pay-success', 'source' => 'request_payment-1', 'target' => 'order_status-1', 'sourceHandle' => 'success'],
                ['id' => 'e-order-journey', 'source' => 'order_status-1', 'target' => 'assign_journey_stage-1'],
                ['id' => 'e-pay-failed', 'source' => 'request_payment-1', 'target' => 'message-4', 'sourceHandle' => 'failed'],
                ['id' => 'e-paid-group', 'source' => 'assign_journey_stage-1', 'target' => 'assign_group-1'],
                ['id' => 'e-group-thanks', 'source' => 'assign_group-1', 'target' => 'message-3'],
                ['id' => 'e-thanks-end', 'source' => 'message-3', 'target' => 'end-1'],
                ['id' => 'e-fail-end', 'source' => 'message-4', 'target' => 'end-1'],
                ['id' => 'e-resolved-end', 'source' => 'message-5', 'target' => 'end-1'],
                ['id' => 'e-agent-group', 'source' => 'assign_agent-1', 'target' => 'assign_group-2'],
                ['id' => 'e-group-handoff', 'source' => 'assign_group-2', 'target' => 'message-6'],
                ['id' => 'e-handoff-end', 'source' => 'message-6', 'target' => 'end-1'],
            ],
        ], [
            'idPrefix' => 'shop-faq',
            'basePosition' => ['x' => 1520, 'y' => 320],
            'introMessage' => "I'm happy to help with product, shipping, and return questions.",
            'questionInitial' => 'What would you like to know?',
            'questionFollowup' => 'Anything else? Reply *checkout* to pay, *browse* or *shop* to see the catalog, *agent* for sales, or *done* when finished.',
            'systemPrompt' => 'You are a helpful shop assistant on WhatsApp. Answer product, shipping, and return questions concisely using order context when available. Never repeat the customer question — always provide a helpful answer. If unsure, suggest speaking with sales.',
            'prompt' => "Customer question:\n{{contact_last_message}}\n\nOrder items (if any): {{catalog_order_items}}",
            'llmVariableName' => 'shop_faq_reply',
            'llmLabel' => 'Shop FAQ bot',
            'counterMax' => 8,
            'freeExecutions' => 5,
            'keywordExits' => [
                ['id' => 'cond-checkout', 'keyword' => 'checkout', 'target' => 'question-1'],
                ['id' => 'cond-browse', 'keyword' => 'browse', 'target' => 'message-1'],
                ['id' => 'cond-shop', 'keyword' => 'shop', 'target' => 'message-1'],
            ],
            'doneTarget' => 'message-5',
            'humanTarget' => 'assign_agent-1',
            'hasIntro' => true,
        ]),
    ],

    'lead_intake_routing' => [
        'name' => 'Lead Intake & Team Routing',
        'description' => 'Qualify leads by service type, collect details, and route to sales, intake, or support teams.',
        'category' => 'sales',
        'video_url' => null,
        'setup_hint' => 'Configure team groups, agents, journey stages. Save draft → Publish before going live.',
        'post_install_checklist' => ['Sales/Intake/Support group IDs', 'Enterprise agent assignment', 'Publish'],
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
                ['id' => 'e-kw1', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'keyword-kw1'],
                ['id' => 'e-kw2', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'keyword-kw2'],
                ['id' => 'e-kw3', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'keyword-kw3'],
                ['id' => 'e-welcome-menu', 'source' => 'message-1', 'target' => 'quick_replies-1'],
                ['id' => 'e-menu-quote', 'source' => 'quick_replies-1', 'target' => 'list_message-1', 'sourceHandle' => 'button-1'],
                ['id' => 'e-menu-existing', 'source' => 'quick_replies-1', 'target' => 'question-4', 'sourceHandle' => 'button-2'],
                ['id' => 'e-menu-general', 'source' => 'quick_replies-1', 'target' => 'lead-faq-question-initial', 'sourceHandle' => 'button-3'],
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
                ['id' => 'e-thanks-end', 'source' => 'message-4', 'target' => 'end-1'],
                ['id' => 'e-agent-support', 'source' => 'assign_agent-2', 'target' => 'assign_group-4'],
                ['id' => 'e-support-handoff', 'source' => 'assign_group-4', 'target' => 'message-5'],
                ['id' => 'e-handoff-end', 'source' => 'message-5', 'target' => 'end-1'],
            ],
        ], [
            'idPrefix' => 'lead-faq',
            'basePosition' => ['x' => 1140, 'y' => 560],
            'questionInitial' => 'What would you like to know about our services?',
            'questionFollowup' => 'Anything else? Reply *quote* to request a proposal, *agent* to speak with someone, or *done* when finished.',
            'systemPrompt' => 'You are a professional services assistant. Answer general questions about the business clearly and concisely. Never repeat the customer question — always provide a helpful answer.',
            'llmVariableName' => 'general_faq_reply',
            'llmLabel' => 'General FAQ',
            'counterMax' => 6,
            'keywordExits' => [
                ['id' => 'cond-quote', 'keyword' => 'quote', 'target' => 'list_message-1'],
            ],
            'doneTarget' => 'message-4',
            'humanTarget' => 'assign_agent-2',
        ]),
    ],

    'support_ai_escalation' => [
        'name' => 'Support Desk — AI Triage & Escalation',
        'description' => 'Category-based support with conversational AI loop, then agent and journey escalation.',
        'category' => 'support',
        'video_url' => null,
        'setup_hint' => 'Train FAQ docs, set Support group/agent/journey. FAQ uses a multi-turn AI loop. Save draft → Publish.',
        'post_install_checklist' => ['OpenRouter key', 'Knowledge base trained', 'Support group & agent', 'Publish'],
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
                            'message' => "You're in the queue. Reference: {{faq_question}}\n\nOur team will reply here on WhatsApp.",
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
                ['id' => 'e-kw1', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'keyword-kw1'],
                ['id' => 'e-kw2', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'keyword-kw2'],
                ['id' => 'e-kw3', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'keyword-kw3'],
                ['id' => 'e-greet-list', 'source' => 'message-1', 'target' => 'list_message-1'],
                ['id' => 'e-cat1-faq', 'source' => 'list_message-1', 'target' => 'support-faq-question-initial', 'sourceHandle' => 'section1-row1'],
                ['id' => 'e-cat2-faq', 'source' => 'list_message-1', 'target' => 'support-faq-question-initial', 'sourceHandle' => 'section1-row2'],
                ['id' => 'e-cat3-faq', 'source' => 'list_message-1', 'target' => 'support-faq-question-initial', 'sourceHandle' => 'section1-row3'],
                ['id' => 'e-cat4-faq', 'source' => 'list_message-1', 'target' => 'support-faq-question-initial', 'sourceHandle' => 'section1-row4'],
                ['id' => 'e-cat5-faq', 'source' => 'list_message-1', 'target' => 'support-faq-question-initial', 'sourceHandle' => 'section1-row5'],
                ['id' => 'e-resolved-end', 'source' => 'message-2', 'target' => 'end-1'],
                ['id' => 'e-agent-path-group', 'source' => 'assign_group-1', 'target' => 'assign_agent-1'],
                ['id' => 'e-group-journey', 'source' => 'assign_agent-1', 'target' => 'assign_journey_stage-1'],
                ['id' => 'e-journey-sla', 'source' => 'assign_journey_stage-1', 'target' => 'message-4'],
                ['id' => 'e-sla-end', 'source' => 'message-4', 'target' => 'end-1'],
            ],
        ], [
            'idPrefix' => 'support-faq',
            'basePosition' => ['x' => 1140, 'y' => 200],
            'questionInitial' => 'Please describe your issue in detail (include order or account references if relevant).',
            'questionFollowup' => 'Did that help? Reply *done* if resolved, or *agent* / *human* to speak with support. Ask another question to continue.',
            'systemPrompt' => 'You are a customer support agent on WhatsApp. Use the knowledge base when available. Be empathetic, concise, and actionable. Never repeat the customer question — always provide a helpful answer. Escalate politely if the issue needs a human.',
            'llmVariableName' => 'support_ai_reply',
            'llmLabel' => 'Support AI',
            'counterMax' => 5,
            'doneTarget' => 'message-2',
            'humanTarget' => 'assign_group-1',
            'limitTarget' => 'assign_group-1',
        ]),
    ],

];

return \App\Services\Flowmaker\FlowTemplateEnricher::enrich(array_merge(
    $flowTemplates,
    require __DIR__.'/flow-templates-industry.php',
    require __DIR__.'/flow-templates-starter.php'
));
