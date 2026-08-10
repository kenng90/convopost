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
        'description' => 'Dynamic spa booking from live Reminders services, dedicated reschedule and cancel paths, AI FAQ, online booking, and team handoff.',
        'category' => 'services',
        'video_url' => null,
        'setup_hint' => 'Create bookable Reminders services with durations, availability, staff, and optional payment or deposit rules. Set Bookings group, journey stage, OpenRouter, and M-Pesa where required. Save draft → Publish.',
        'post_install_checklist' => [
            'Bookable Reminders services, schedules, and staff',
            'Payment or deposit rules on each paid service',
            'M-Pesa configured when a service requires payment',
            'Bookings group & journey stage IDs',
            'OpenRouter key and spa knowledge base for FAQ',
            'Publish flow',
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
                            ['id' => 'kw1', 'value' => 'book', 'matchType' => 'contains'],
                            ['id' => 'kw2', 'value' => 'spa', 'matchType' => 'contains'],
                            ['id' => 'kw3', 'value' => 'appointment', 'matchType' => 'contains'],
                            ['id' => 'kw4', 'value' => 'reschedule', 'matchType' => 'contains'],
                            ['id' => 'kw5', 'value' => 'cancel', 'matchType' => 'contains'],
                            ['id' => 'kw6', 'value' => 'help', 'matchType' => 'exact'],
                            ['id' => 'kw7', 'value' => 'agent', 'matchType' => 'exact'],
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
                            'message' => "Welcome to our spa! 🌿\n\nBook from our live treatment schedule, change an existing appointment, or ask our spa concierge a question.\n\nAvailability and prices shown during booking come directly from our current service calendar.",
                        ],
                    ],
                ],
                [
                    'id' => 'list_message-1',
                    'type' => 'list_message',
                    'position' => ['x' => 760, 'y' => 200],
                    'data' => [
                        'label' => 'Spa concierge menu',
                        'type' => 'list_message',
                        'settings' => [
                            'header' => 'Spa & Wellness',
                            'body' => 'How can we help today?',
                            'footer' => 'Reply *spa* anytime to return here.',
                            'buttonText' => 'Choose an option',
                            'sections' => [
                                [
                                    'id' => 'section1',
                                    'title' => 'Appointments',
                                    'rows' => [
                                        ['id' => 'row1', 'title' => 'Book a treatment', 'description' => 'Live services, dates, and times'],
                                        ['id' => 'row2', 'title' => 'Reschedule', 'description' => 'Pick a new live date and time'],
                                        ['id' => 'row3', 'title' => 'Cancel appointment', 'description' => 'Cancel your next visit'],
                                        ['id' => 'row4', 'title' => 'Ask a question', 'description' => 'Treatments, preparation, and policies'],
                                        ['id' => 'row5', 'title' => 'Book online', 'description' => 'Open our full booking page'],
                                        ['id' => 'row6', 'title' => 'Talk to our team', 'description' => 'Get help from a person'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'book_appointment-1',
                    'type' => 'book_appointment',
                    'position' => ['x' => 1140, 'y' => 40],
                    'data' => [
                        'label' => 'Live treatment booking',
                        'type' => 'book_appointment',
                        'settings' => [
                            'source_name' => '',
                            'duration_minutes' => '',
                            'intake_mode' => 'lists',
                            'header' => 'Book your visit',
                            'body' => 'Choose a live service, duration, date, and available time.',
                            'footer' => 'Paid services request a deposit first.',
                            'buttonText' => 'Choose',
                            'allow_pay_at_venue' => false,
                            'allow_payment_retry' => true,
                            'success_message' => 'Confirmed ✅ {{booking_service}} on {{booking_date}} at {{booking_time}}. Booking reference: {{booking_reference}}.',
                        ],
                    ],
                ],
                [
                    'id' => 'assign_group-1',
                    'type' => 'assign_group',
                    'position' => ['x' => 1520, 'y' => 40],
                    'data' => [
                        'label' => 'Assign Bookings team',
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
                    'position' => ['x' => 1900, 'y' => 40],
                    'data' => [
                        'label' => 'Move to Booked',
                        'type' => 'assign_journey_stage',
                        'settings' => [
                            'journeyId' => '1',
                            'stageId' => '1',
                        ],
                    ],
                ],
                [
                    'id' => 'question-1',
                    'type' => 'question',
                    'position' => ['x' => 2280, 'y' => 40],
                    'data' => [
                        'label' => 'Treatment preferences',
                        'type' => 'question',
                        'settings' => [
                            'question' => 'Any allergies, accessibility needs, therapist preference, or special notes? Reply *none* if not applicable.',
                            'variableName' => 'spa_booking_notes',
                        ],
                    ],
                ],
                [
                    'id' => 'message-2',
                    'type' => 'message',
                    'position' => ['x' => 2660, 'y' => 40],
                    'data' => [
                        'label' => 'Preferences received',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'Thank you. We saved your appointment notes and look forward to welcoming you. Reply *reschedule* or *cancel* if your plans change.',
                        ],
                    ],
                ],
                [
                    'id' => 'message-book-unavailable',
                    'type' => 'message',
                    'position' => ['x' => 1520, 'y' => 240],
                    'data' => [
                        'label' => 'No appointments available',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'Sorry, no matching dates are open right now. You can try another treatment, use our online calendar, or ask the team for help.',
                        ],
                    ],
                ],
                [
                    'id' => 'message-book-error',
                    'type' => 'message',
                    'position' => ['x' => 1520, 'y' => 400],
                    'data' => [
                        'label' => 'Booking could not complete',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'We could not complete that booking. No charge or duplicate appointment should be assumed. Please retry, book online, or contact our team.',
                        ],
                    ],
                ],
                [
                    'id' => 'quick_replies-book-recovery',
                    'type' => 'quick_replies',
                    'position' => ['x' => 1900, 'y' => 300],
                    'data' => [
                        'label' => 'Booking recovery',
                        'type' => 'quick_replies',
                        'settings' => [
                            'header' => 'What next?',
                            'body' => 'Choose another way to continue.',
                            'footer' => null,
                            'activeButtons' => 3,
                            'button1' => 'Try booking again',
                            'button2' => 'Book online',
                            'button3' => 'Talk to team',
                        ],
                    ],
                ],
                [
                    'id' => 'manage_booking-reschedule',
                    'type' => 'manage_booking',
                    'position' => ['x' => 1140, 'y' => 520],
                    'data' => [
                        'label' => 'Reschedule appointment',
                        'type' => 'manage_booking',
                        'settings' => [
                            'reference_variable' => 'booking_reference',
                            'default_action' => 'reschedule',
                            'allow_reschedule' => true,
                            'header' => 'Reschedule your visit',
                            'body' => 'Choose a new live date and time for your appointment.',
                            'buttonText' => 'Choose date',
                        ],
                    ],
                ],
                [
                    'id' => 'manage_booking-1',
                    'type' => 'manage_booking',
                    'position' => ['x' => 1140, 'y' => 760],
                    'data' => [
                        'label' => 'Cancel appointment',
                        'type' => 'manage_booking',
                        'settings' => [
                            'reference_variable' => 'booking_reference',
                            'default_action' => 'cancel',
                            'allow_reschedule' => false,
                            'header' => 'Cancel your appointment',
                            'body' => 'Confirm if you want to cancel your next visit.',
                            'buttonText' => 'Confirm',
                        ],
                    ],
                ],
                [
                    'id' => 'message-rescheduled',
                    'type' => 'message',
                    'position' => ['x' => 1520, 'y' => 520],
                    'data' => [
                        'label' => 'Reschedule complete',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'Your spa appointment was updated. Reply *spa* for anything else.',
                        ],
                    ],
                ],
                [
                    'id' => 'message-cancelled',
                    'type' => 'message',
                    'position' => ['x' => 1520, 'y' => 680],
                    'data' => [
                        'label' => 'Cancellation complete',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'Your appointment was cancelled. Reply *book* whenever you are ready to schedule another visit.',
                        ],
                    ],
                ],
                [
                    'id' => 'message-manage-not-found',
                    'type' => 'message',
                    'position' => ['x' => 1520, 'y' => 840],
                    'data' => [
                        'label' => 'No booking found',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'We could not find an active upcoming appointment for this WhatsApp number. You can book a new visit or ask the team to check manually.',
                        ],
                    ],
                ],
                [
                    'id' => 'message-manage-error',
                    'type' => 'message',
                    'position' => ['x' => 1520, 'y' => 1000],
                    'data' => [
                        'label' => 'Manage booking error',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'We could not update that appointment safely. Our team can help verify its current status.',
                        ],
                    ],
                ],
                [
                    'id' => 'quick_replies-manage-recovery',
                    'type' => 'quick_replies',
                    'position' => ['x' => 1900, 'y' => 900],
                    'data' => [
                        'label' => 'Management recovery',
                        'type' => 'quick_replies',
                        'settings' => [
                            'header' => 'How should we help?',
                            'body' => 'Choose an option below.',
                            'footer' => null,
                            'activeButtons' => 3,
                            'button1' => 'Book new visit',
                            'button2' => 'Book online',
                            'button3' => 'Talk to team',
                        ],
                    ],
                ],
                [
                    'id' => 'send_booking_link-1',
                    'type' => 'send_booking_link',
                    'position' => ['x' => 1140, 'y' => 1120],
                    'data' => [
                        'label' => 'Send online calendar',
                        'type' => 'send_booking_link',
                        'settings' => [
                            'link_type' => 'appointments',
                            'header' => 'Online spa booking',
                            'message' => 'Browse all live services and available times here: {{booking_link}}',
                            'footer' => 'Return to WhatsApp and reply *spa* if you need help.',
                        ],
                    ],
                ],
                [
                    'id' => 'message-link-error',
                    'type' => 'message',
                    'position' => ['x' => 1520, 'y' => 1160],
                    'data' => [
                        'label' => 'Online booking unavailable',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'Our online calendar is unavailable right now. We will connect you with the bookings team.',
                        ],
                    ],
                ],
                [
                    'id' => 'assign_agent-1',
                    'type' => 'assign_agent',
                    'position' => ['x' => 2280, 'y' => 1120],
                    'data' => [
                        'label' => 'Assign spa agent',
                        'type' => 'assign_agent',
                        'settings' => [
                            'agentId' => 'none',
                        ],
                    ],
                ],
                [
                    'id' => 'assign_group-2',
                    'type' => 'assign_group',
                    'position' => ['x' => 2660, 'y' => 1120],
                    'data' => [
                        'label' => 'Assign Bookings team',
                        'type' => 'assign_group',
                        'settings' => [
                            'groupId' => '1',
                            'action' => 'add',
                        ],
                    ],
                ],
                [
                    'id' => 'message-handoff',
                    'type' => 'message',
                    'position' => ['x' => 3040, 'y' => 1120],
                    'data' => [
                        'label' => 'Team handoff',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'A member of our spa bookings team will reply here shortly. Please share any useful booking reference or preferred treatment.',
                        ],
                    ],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 3420, 'y' => 600],
                    'data' => ['label' => 'End', 'type' => 'end'],
                ],
            ],
            'edges' => [
                ['id' => 'e-kw1', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'keyword-kw1'],
                ['id' => 'e-kw2', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'keyword-kw2'],
                ['id' => 'e-kw3', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'keyword-kw3'],
                ['id' => 'e-kw4-manage', 'source' => 'keyword_trigger-1', 'target' => 'manage_booking-reschedule', 'sourceHandle' => 'keyword-kw4'],
                ['id' => 'e-kw5-manage', 'source' => 'keyword_trigger-1', 'target' => 'manage_booking-1', 'sourceHandle' => 'keyword-kw5'],
                ['id' => 'e-kw6-faq', 'source' => 'keyword_trigger-1', 'target' => 'spa-faq-question-initial', 'sourceHandle' => 'keyword-kw6'],
                ['id' => 'e-kw7-agent', 'source' => 'keyword_trigger-1', 'target' => 'assign_agent-1', 'sourceHandle' => 'keyword-kw7'],
                ['id' => 'e-welcome-menu', 'source' => 'message-1', 'target' => 'list_message-1'],
                ['id' => 'e-menu-book', 'source' => 'list_message-1', 'target' => 'book_appointment-1', 'sourceHandle' => 'section1-row1'],
                ['id' => 'e-menu-reschedule', 'source' => 'list_message-1', 'target' => 'manage_booking-reschedule', 'sourceHandle' => 'section1-row2'],
                ['id' => 'e-menu-cancel', 'source' => 'list_message-1', 'target' => 'manage_booking-1', 'sourceHandle' => 'section1-row3'],
                ['id' => 'e-menu-faq', 'source' => 'list_message-1', 'target' => 'spa-faq-question-initial', 'sourceHandle' => 'section1-row4'],
                ['id' => 'e-menu-link', 'source' => 'list_message-1', 'target' => 'send_booking_link-1', 'sourceHandle' => 'section1-row5'],
                ['id' => 'e-menu-agent', 'source' => 'list_message-1', 'target' => 'assign_agent-1', 'sourceHandle' => 'section1-row6'],
                ['id' => 'e-book-success', 'source' => 'book_appointment-1', 'target' => 'assign_group-1', 'sourceHandle' => 'success'],
                ['id' => 'e-book-unavailable', 'source' => 'book_appointment-1', 'target' => 'message-book-unavailable', 'sourceHandle' => 'unavailable'],
                ['id' => 'e-book-error', 'source' => 'book_appointment-1', 'target' => 'message-book-error', 'sourceHandle' => 'error'],
                ['id' => 'e-unavailable-recovery', 'source' => 'message-book-unavailable', 'target' => 'quick_replies-book-recovery'],
                ['id' => 'e-error-recovery', 'source' => 'message-book-error', 'target' => 'quick_replies-book-recovery'],
                ['id' => 'e-recovery-retry', 'source' => 'quick_replies-book-recovery', 'target' => 'book_appointment-1', 'sourceHandle' => 'button-1'],
                ['id' => 'e-recovery-link', 'source' => 'quick_replies-book-recovery', 'target' => 'send_booking_link-1', 'sourceHandle' => 'button-2'],
                ['id' => 'e-recovery-agent', 'source' => 'quick_replies-book-recovery', 'target' => 'assign_agent-1', 'sourceHandle' => 'button-3'],
                ['id' => 'e-group-journey', 'source' => 'assign_group-1', 'target' => 'assign_journey_stage-1'],
                ['id' => 'e-journey-notes', 'source' => 'assign_journey_stage-1', 'target' => 'question-1'],
                ['id' => 'e-notes-thanks', 'source' => 'question-1', 'target' => 'message-2'],
                ['id' => 'e-booking-complete', 'source' => 'message-2', 'target' => 'end-1'],
                ['id' => 'e-reschedule-done', 'source' => 'manage_booking-reschedule', 'target' => 'message-rescheduled', 'sourceHandle' => 'rescheduled'],
                ['id' => 'e-reschedule-not-found', 'source' => 'manage_booking-reschedule', 'target' => 'message-manage-not-found', 'sourceHandle' => 'not_found'],
                ['id' => 'e-reschedule-error', 'source' => 'manage_booking-reschedule', 'target' => 'message-manage-error', 'sourceHandle' => 'error'],
                ['id' => 'e-manage-cancelled', 'source' => 'manage_booking-1', 'target' => 'message-cancelled', 'sourceHandle' => 'cancelled'],
                ['id' => 'e-manage-not-found', 'source' => 'manage_booking-1', 'target' => 'message-manage-not-found', 'sourceHandle' => 'not_found'],
                ['id' => 'e-manage-error', 'source' => 'manage_booking-1', 'target' => 'message-manage-error', 'sourceHandle' => 'error'],
                ['id' => 'e-rescheduled-end', 'source' => 'message-rescheduled', 'target' => 'end-1'],
                ['id' => 'e-cancelled-end', 'source' => 'message-cancelled', 'target' => 'end-1'],
                ['id' => 'e-not-found-recovery', 'source' => 'message-manage-not-found', 'target' => 'quick_replies-manage-recovery'],
                ['id' => 'e-manage-error-recovery', 'source' => 'message-manage-error', 'target' => 'quick_replies-manage-recovery'],
                ['id' => 'e-manage-recovery-book', 'source' => 'quick_replies-manage-recovery', 'target' => 'book_appointment-1', 'sourceHandle' => 'button-1'],
                ['id' => 'e-manage-recovery-link', 'source' => 'quick_replies-manage-recovery', 'target' => 'send_booking_link-1', 'sourceHandle' => 'button-2'],
                ['id' => 'e-manage-recovery-agent', 'source' => 'quick_replies-manage-recovery', 'target' => 'assign_agent-1', 'sourceHandle' => 'button-3'],
                ['id' => 'e-link-success', 'source' => 'send_booking_link-1', 'target' => 'end-1', 'sourceHandle' => 'success'],
                ['id' => 'e-link-error', 'source' => 'send_booking_link-1', 'target' => 'message-link-error', 'sourceHandle' => 'error'],
                ['id' => 'e-link-error-agent', 'source' => 'message-link-error', 'target' => 'assign_agent-1'],
                ['id' => 'e-agent-group', 'source' => 'assign_agent-1', 'target' => 'assign_group-2'],
                ['id' => 'e-agent-handoff', 'source' => 'assign_group-2', 'target' => 'message-handoff'],
                ['id' => 'e-handoff-end', 'source' => 'message-handoff', 'target' => 'end-1'],
            ],
        ], [
            'idPrefix' => 'spa-faq',
            'basePosition' => ['x' => 1140, 'y' => 1360],
            'questionInitial' => 'What would you like to know about our treatments, preparation, payments, accessibility, or spa policies?',
            'questionFollowup' => 'Anything else? Ask another question, or reply *book*, *reschedule*, *cancel*, *online*, *spa*, *agent*, or *done*.',
            'systemPrompt' => 'You are a careful spa and wellness concierge on WhatsApp. Answer treatment, preparation, payment, accessibility, cancellation, and policy questions concisely using the knowledge base. Never diagnose medical conditions, promise clinical outcomes, invent prices, or repeat the customer question. Recommend professional medical advice when a health condition may affect treatment.',
            'llmVariableName' => 'spa_faq_reply',
            'llmLabel' => 'Spa FAQ concierge',
            'counterMax' => 8,
            'freeExecutions' => 5,
            'keywordExits' => [
                ['id' => 'cond-book', 'keyword' => 'book', 'target' => 'book_appointment-1'],
                ['id' => 'cond-reschedule', 'keyword' => 'reschedule', 'target' => 'manage_booking-reschedule'],
                ['id' => 'cond-cancel', 'keyword' => 'cancel', 'target' => 'manage_booking-1'],
                ['id' => 'cond-online', 'keyword' => 'online', 'target' => 'send_booking_link-1'],
                ['id' => 'cond-spa', 'keyword' => 'spa', 'target' => 'list_message-1'],
                ['id' => 'cond-menu', 'keyword' => 'menu', 'target' => 'list_message-1'],
            ],
            'doneTarget' => 'end-1',
            'humanTarget' => 'assign_agent-1',
            'limitTarget' => 'assign_agent-1',
        ]),
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
                            ['id' => 'kw3', 'value' => 'help', 'matchType' => 'exact'],
                            ['id' => 'kw4', 'value' => 'agent', 'matchType' => 'exact'],
                            ['id' => 'kw5', 'value' => 'sales', 'matchType' => 'contains'],
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
                            'message' => "Welcome to our shop!\n\nBrowse products below, then checkout, ask a question, or talk to sales. Reply *shop* anytime to start over.",
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
                            'question' => 'Any order notes? (size, colour, quantity, etc.) Reply *none* if not needed.',
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
                            'message' => "Order summary\n\nItems: {{catalog_order_items}}\nTotal: {{catalog_order_total_amount}}\nDelivery: {{delivery_address}}\nNotes: {{order_notes}}\n\nConfirm to pay, or edit your details.",
                        ],
                    ],
                ],
                [
                    'id' => 'quick_replies-confirm-pay',
                    'type' => 'quick_replies',
                    'position' => ['x' => 2470, 'y' => 80],
                    'data' => [
                        'label' => 'Confirm payment',
                        'type' => 'quick_replies',
                        'settings' => [
                            'header' => 'Ready to pay?',
                            'body' => 'Confirm to send the payment request for {{catalog_order_total_amount}}.',
                            'footer' => 'Reply *shop* to cancel.',
                            'activeButtons' => 3,
                            'button1' => 'Confirm & pay',
                            'button2' => 'Edit details',
                            'button3' => 'Talk to sales',
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
                            'message' => 'Payment received! Your order is now *{{order_status}}*. Reference: {{order_reference}}',
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
                            'message' => 'Payment was not completed. No charge should be assumed. Choose what to do next.',
                        ],
                    ],
                ],
                [
                    'id' => 'quick_replies-pay-recovery',
                    'type' => 'quick_replies',
                    'position' => ['x' => 3040, 'y' => 280],
                    'data' => [
                        'label' => 'Payment recovery',
                        'type' => 'quick_replies',
                        'settings' => [
                            'header' => 'What next?',
                            'body' => 'Retry payment, edit your order details, or talk to sales.',
                            'footer' => 'Reply *shop* to start over.',
                            'activeButtons' => 3,
                            'button1' => 'Retry payment',
                            'button2' => 'Edit details',
                            'button3' => 'Talk to sales',
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
                ['id' => 'e-kw3-faq', 'source' => 'keyword_trigger-1', 'target' => 'shop-faq-message-intro', 'sourceHandle' => 'keyword-kw3'],
                ['id' => 'e-kw4-sales', 'source' => 'keyword_trigger-1', 'target' => 'assign_agent-1', 'sourceHandle' => 'keyword-kw4'],
                ['id' => 'e-kw5-sales', 'source' => 'keyword_trigger-1', 'target' => 'assign_agent-1', 'sourceHandle' => 'keyword-kw5'],
                ['id' => 'e-intro-catalog', 'source' => 'message-1', 'target' => 'whatsapp_catalog-1'],
                ['id' => 'e-catalog-menu', 'source' => 'whatsapp_catalog-1', 'target' => 'quick_replies-1', 'sourceHandle' => 'onProductSelected'],
                ['id' => 'e-menu-checkout', 'source' => 'quick_replies-1', 'target' => 'question-1', 'sourceHandle' => 'button-1'],
                ['id' => 'e-menu-faq', 'source' => 'quick_replies-1', 'target' => 'shop-faq-message-intro', 'sourceHandle' => 'button-2'],
                ['id' => 'e-menu-sales', 'source' => 'quick_replies-1', 'target' => 'assign_agent-1', 'sourceHandle' => 'button-3'],
                ['id' => 'e-addr-notes', 'source' => 'question-1', 'target' => 'question-2'],
                ['id' => 'e-notes-summary', 'source' => 'question-2', 'target' => 'message-2'],
                ['id' => 'e-summary-confirm', 'source' => 'message-2', 'target' => 'quick_replies-confirm-pay'],
                ['id' => 'e-confirm-pay', 'source' => 'quick_replies-confirm-pay', 'target' => 'request_payment-1', 'sourceHandle' => 'button-1'],
                ['id' => 'e-confirm-edit', 'source' => 'quick_replies-confirm-pay', 'target' => 'question-1', 'sourceHandle' => 'button-2'],
                ['id' => 'e-confirm-sales', 'source' => 'quick_replies-confirm-pay', 'target' => 'assign_agent-1', 'sourceHandle' => 'button-3'],
                ['id' => 'e-pay-success', 'source' => 'request_payment-1', 'target' => 'order_status-1', 'sourceHandle' => 'success'],
                ['id' => 'e-order-journey', 'source' => 'order_status-1', 'target' => 'assign_journey_stage-1'],
                ['id' => 'e-pay-failed', 'source' => 'request_payment-1', 'target' => 'message-4', 'sourceHandle' => 'failed'],
                ['id' => 'e-paid-group', 'source' => 'assign_journey_stage-1', 'target' => 'assign_group-1'],
                ['id' => 'e-group-thanks', 'source' => 'assign_group-1', 'target' => 'message-3'],
                ['id' => 'e-thanks-end', 'source' => 'message-3', 'target' => 'end-1'],
                ['id' => 'e-fail-recovery', 'source' => 'message-4', 'target' => 'quick_replies-pay-recovery'],
                ['id' => 'e-retry-pay', 'source' => 'quick_replies-pay-recovery', 'target' => 'request_payment-1', 'sourceHandle' => 'button-1'],
                ['id' => 'e-fail-edit', 'source' => 'quick_replies-pay-recovery', 'target' => 'question-1', 'sourceHandle' => 'button-2'],
                ['id' => 'e-fail-sales', 'source' => 'quick_replies-pay-recovery', 'target' => 'assign_agent-1', 'sourceHandle' => 'button-3'],
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
            'questionFollowup' => 'Anything else? Reply *checkout* to pay, *browse* or *shop* for the catalog, *agent* for sales, or *done* when finished.',
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
                            ['id' => 'kw4', 'value' => 'agent', 'matchType' => 'exact'],
                            ['id' => 'kw5', 'value' => 'help', 'matchType' => 'exact'],
                            ['id' => 'kw6', 'value' => 'existing', 'matchType' => 'contains'],
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
                            'message' => "Thanks for reaching out!\n\nWe typically respond within one business day. Pick an option below, or reply *agent* to speak with someone now.",
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
                            'footer' => 'Reply *quote* to start over.',
                            'activeButtons' => 3,
                            'button1' => 'Get a quote',
                            'button2' => 'Existing client',
                            'button3' => 'Talk to someone',
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
                            'footer' => 'Reply *quote* to start over.',
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
                    'id' => 'question-service',
                    'type' => 'question',
                    'position' => ['x' => 1330, 'y' => 80],
                    'data' => [
                        'label' => 'Confirm service',
                        'type' => 'question',
                        'settings' => [
                            'question' => "You selected *{{contact_last_message}}*.\n\nReply *yes* to continue, or type a different service name.",
                            'variableName' => 'lead_service',
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
                    'id' => 'message-lead-review',
                    'type' => 'message',
                    'position' => ['x' => 2470, 'y' => 80],
                    'data' => [
                        'label' => 'Review details',
                        'type' => 'message',
                        'settings' => [
                            'message' => "Please confirm your request:\n\n• Name: {{lead_name}}\n• Service: {{lead_service}}\n• Need: {{lead_need}}\n• Location: {{lead_location}}",
                        ],
                    ],
                ],
                [
                    'id' => 'quick_replies-lead-confirm',
                    'type' => 'quick_replies',
                    'position' => ['x' => 2660, 'y' => 80],
                    'data' => [
                        'label' => 'Confirm submit',
                        'type' => 'quick_replies',
                        'settings' => [
                            'header' => 'Ready to submit?',
                            'body' => 'Submit your request, edit details, or talk to someone.',
                            'footer' => 'Reply *quote* to start over.',
                            'activeButtons' => 3,
                            'button1' => 'Submit request',
                            'button2' => 'Edit details',
                            'button3' => 'Talk to someone',
                        ],
                    ],
                ],
                [
                    'id' => 'datastore-1',
                    'type' => 'datastore',
                    'position' => ['x' => 2850, 'y' => 80],
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
                            'message' => "Thank you {{lead_name}}!\n\nWe received your *{{lead_service}}* request and will send a quote within 24 hours.\n\nNeed: {{lead_need}}\nLocation: {{lead_location}}",
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
                    'id' => 'quick_replies-existing-recovery',
                    'type' => 'quick_replies',
                    'position' => ['x' => 1330, 'y' => 360],
                    'data' => [
                        'label' => 'Existing client next',
                        'type' => 'quick_replies',
                        'settings' => [
                            'header' => 'What next?',
                            'body' => 'We have reference {{account_reference}}. How should we help?',
                            'footer' => 'Reply *quote* for a new request.',
                            'activeButtons' => 3,
                            'button1' => 'Continue lookup',
                            'button2' => 'I need help',
                            'button3' => 'Ask a question',
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
                            'message' => 'Happy to help! Reply *quote* anytime to request a formal proposal, or *agent* to speak with someone.',
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
                ['id' => 'e-kw4', 'source' => 'keyword_trigger-1', 'target' => 'assign_agent-2', 'sourceHandle' => 'keyword-kw4'],
                ['id' => 'e-kw5', 'source' => 'keyword_trigger-1', 'target' => 'lead-faq-question-initial', 'sourceHandle' => 'keyword-kw5'],
                ['id' => 'e-kw6', 'source' => 'keyword_trigger-1', 'target' => 'question-4', 'sourceHandle' => 'keyword-kw6'],
                ['id' => 'e-welcome-menu', 'source' => 'message-1', 'target' => 'quick_replies-1'],
                ['id' => 'e-menu-quote', 'source' => 'quick_replies-1', 'target' => 'list_message-1', 'sourceHandle' => 'button-1'],
                ['id' => 'e-menu-existing', 'source' => 'quick_replies-1', 'target' => 'question-4', 'sourceHandle' => 'button-2'],
                ['id' => 'e-menu-talk', 'source' => 'quick_replies-1', 'target' => 'assign_agent-2', 'sourceHandle' => 'button-3'],
                ['id' => 'e-list-consult', 'source' => 'list_message-1', 'target' => 'question-service', 'sourceHandle' => 'section1-row1'],
                ['id' => 'e-list-install', 'source' => 'list_message-1', 'target' => 'question-service', 'sourceHandle' => 'section1-row2'],
                ['id' => 'e-list-maint', 'source' => 'list_message-1', 'target' => 'question-service', 'sourceHandle' => 'section1-row3'],
                ['id' => 'e-list-enterprise', 'source' => 'list_message-1', 'target' => 'question-service', 'sourceHandle' => 'section1-row4'],
                ['id' => 'e-svc-name', 'source' => 'question-service', 'target' => 'question-1'],
                ['id' => 'e-q1-q2', 'source' => 'question-1', 'target' => 'question-2'],
                ['id' => 'e-q2-q3', 'source' => 'question-2', 'target' => 'question-3'],
                ['id' => 'e-q3-review', 'source' => 'question-3', 'target' => 'message-lead-review'],
                ['id' => 'e-review-confirm', 'source' => 'message-lead-review', 'target' => 'quick_replies-lead-confirm'],
                ['id' => 'e-lead-submit', 'source' => 'quick_replies-lead-confirm', 'target' => 'datastore-1', 'sourceHandle' => 'button-1'],
                ['id' => 'e-lead-edit', 'source' => 'quick_replies-lead-confirm', 'target' => 'question-service', 'sourceHandle' => 'button-2'],
                ['id' => 'e-lead-talk', 'source' => 'quick_replies-lead-confirm', 'target' => 'assign_agent-2', 'sourceHandle' => 'button-3'],
                ['id' => 'e-store-intake', 'source' => 'datastore-1', 'target' => 'assign_group-1'],
                ['id' => 'e-intake-agent', 'source' => 'assign_group-1', 'target' => 'assign_agent-1'],
                ['id' => 'e-agent-journey', 'source' => 'assign_agent-1', 'target' => 'assign_journey_stage-1'],
                ['id' => 'e-journey-confirm', 'source' => 'assign_journey_stage-1', 'target' => 'message-2'],
                ['id' => 'e-confirm-end', 'source' => 'message-2', 'target' => 'end-1'],
                ['id' => 'e-ref-recovery', 'source' => 'question-4', 'target' => 'quick_replies-existing-recovery'],
                ['id' => 'e-existing-lookup', 'source' => 'quick_replies-existing-recovery', 'target' => 'assign_group-3', 'sourceHandle' => 'button-1'],
                ['id' => 'e-existing-help', 'source' => 'quick_replies-existing-recovery', 'target' => 'assign_agent-2', 'sourceHandle' => 'button-2'],
                ['id' => 'e-existing-faq', 'source' => 'quick_replies-existing-recovery', 'target' => 'lead-faq-question-initial', 'sourceHandle' => 'button-3'],
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
            'questionFollowup' => 'Anything else? Reply *quote* for a proposal, *existing* for account help, *menu* for options, *agent*, or *done*.',
            'systemPrompt' => 'You are a professional services assistant. Answer general questions about the business clearly and concisely. Never repeat the customer question — always provide a helpful answer.',
            'llmVariableName' => 'general_faq_reply',
            'llmLabel' => 'General FAQ',
            'counterMax' => 6,
            'keywordExits' => [
                ['id' => 'cond-quote', 'keyword' => 'quote', 'target' => 'list_message-1'],
                ['id' => 'cond-existing', 'keyword' => 'existing', 'target' => 'question-4'],
                ['id' => 'cond-menu', 'keyword' => 'menu', 'target' => 'quick_replies-1'],
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
                            ['id' => 'kw4', 'value' => 'agent', 'matchType' => 'exact'],
                            ['id' => 'kw5', 'value' => 'human', 'matchType' => 'exact'],
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
                            'message' => "Support team here.\n\nPick a category below. Reply *agent* anytime to skip to a human.",
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
                            'footer' => 'Reply *agent* for a human.',
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
                    'id' => 'question-category',
                    'type' => 'question',
                    'position' => ['x' => 950, 'y' => 200],
                    'data' => [
                        'label' => 'Confirm category',
                        'type' => 'question',
                        'settings' => [
                            'question' => "Category noted: *{{contact_last_message}}*.\n\nReply *yes* to continue, or type a clearer category.",
                            'variableName' => 'support_category',
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
                            'message' => 'Glad we could help! Choose an option below, or reply *help* anytime.',
                        ],
                    ],
                ],
                [
                    'id' => 'quick_replies-resolved',
                    'type' => 'quick_replies',
                    'position' => ['x' => 2090, 'y' => 120],
                    'data' => [
                        'label' => 'After resolved',
                        'type' => 'quick_replies',
                        'settings' => [
                            'header' => 'Anything else?',
                            'body' => 'Ask another question, talk to an agent, or finish.',
                            'footer' => 'Reply *help* to start over.',
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
                            'message' => "You're in the support queue.\n\nCategory: {{support_category}}\n\nOur team will reply here on WhatsApp shortly.",
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
                ['id' => 'e-kw4', 'source' => 'keyword_trigger-1', 'target' => 'assign_group-1', 'sourceHandle' => 'keyword-kw4'],
                ['id' => 'e-kw5', 'source' => 'keyword_trigger-1', 'target' => 'assign_group-1', 'sourceHandle' => 'keyword-kw5'],
                ['id' => 'e-greet-list', 'source' => 'message-1', 'target' => 'list_message-1'],
                ['id' => 'e-cat1', 'source' => 'list_message-1', 'target' => 'question-category', 'sourceHandle' => 'section1-row1'],
                ['id' => 'e-cat2', 'source' => 'list_message-1', 'target' => 'question-category', 'sourceHandle' => 'section1-row2'],
                ['id' => 'e-cat3', 'source' => 'list_message-1', 'target' => 'question-category', 'sourceHandle' => 'section1-row3'],
                ['id' => 'e-cat4', 'source' => 'list_message-1', 'target' => 'question-category', 'sourceHandle' => 'section1-row4'],
                ['id' => 'e-cat5', 'source' => 'list_message-1', 'target' => 'question-category', 'sourceHandle' => 'section1-row5'],
                ['id' => 'e-cat-faq', 'source' => 'question-category', 'target' => 'support-faq-question-initial'],
                ['id' => 'e-resolved-next', 'source' => 'message-2', 'target' => 'quick_replies-resolved'],
                ['id' => 'e-ask-again', 'source' => 'quick_replies-resolved', 'target' => 'support-faq-question-initial', 'sourceHandle' => 'button-1'],
                ['id' => 'e-resolved-agent', 'source' => 'quick_replies-resolved', 'target' => 'assign_group-1', 'sourceHandle' => 'button-2'],
                ['id' => 'e-resolved-done', 'source' => 'quick_replies-resolved', 'target' => 'end-1', 'sourceHandle' => 'button-3'],
                ['id' => 'e-agent-path-group', 'source' => 'assign_group-1', 'target' => 'assign_agent-1'],
                ['id' => 'e-group-journey', 'source' => 'assign_agent-1', 'target' => 'assign_journey_stage-1'],
                ['id' => 'e-journey-sla', 'source' => 'assign_journey_stage-1', 'target' => 'message-4'],
                ['id' => 'e-sla-end', 'source' => 'message-4', 'target' => 'end-1'],
            ],
        ], [
            'idPrefix' => 'support-faq',
            'basePosition' => ['x' => 1140, 'y' => 200],
            'questionInitial' => 'Please describe your issue in detail (include order or account references if relevant).',
            'questionFollowup' => 'Did that help? Reply *done* if resolved, *agent* / *human* for support, *menu* for categories, or ask another question.',
            'systemPrompt' => 'You are a customer support agent on WhatsApp. Category: {{support_category}}. Use the knowledge base when available. Be empathetic, concise, and actionable. Never repeat the customer question — always provide a helpful answer. Escalate politely if the issue needs a human.',
            'prompt' => "Category: {{support_category}}\n\nCustomer message:\n{{contact_last_message}}",
            'llmVariableName' => 'support_ai_reply',
            'llmLabel' => 'Support AI',
            'counterMax' => 5,
            'keywordExits' => [
                ['id' => 'cond-menu', 'keyword' => 'menu', 'target' => 'list_message-1'],
                ['id' => 'cond-categories', 'keyword' => 'categories', 'target' => 'list_message-1'],
            ],
            'doneTarget' => 'message-2',
            'humanTarget' => 'assign_group-1',
            'limitTarget' => 'assign_group-1',
        ]),
    ],

];

return \App\Services\Flowmaker\FlowTemplateOmniConverter::appendOmniVariants(
    \App\Services\Flowmaker\FlowTemplateEnricher::enrich(array_merge(
        $flowTemplates,
        require __DIR__.'/flow-templates-industry.php',
        require __DIR__.'/flow-templates-starter.php'
    ))
);
