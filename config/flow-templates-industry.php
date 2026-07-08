<?php

use App\Services\Flowmaker\FaqConversationLoop;

/**
 * Industry vertical flow templates (healthcare, real estate, banking, hospitality).
 *
 * @return array<string, array<string, mixed>>
 */
return [

    'healthcare_clinic_bot' => [
        'name' => 'Healthcare Clinic Bot',
        'description' => 'Appointments via WhatsApp Flow, triage AI conversation loop, lab results lookup, and service menu.',
        'category' => 'healthcare',
        'form_bundle' => 'healthcare_appointment',
        'video_url' => null,
        'setup_hint' => 'Link WhatsApp Flow, lab API, OpenRouter key, Results team. Triage AI uses a multi-turn loop. Save draft → Publish.',
        'post_install_checklist' => ['WhatsApp Flow ID', 'Lab API URL & token', 'OpenRouter key', 'Results team group', 'Publish'],
        'flow_data' => FaqConversationLoop::mergeInto([
            'nodes' => [
                [
                    'id' => 'keyword_trigger-1',
                    'type' => 'keyword_trigger',
                    'position' => ['x' => 0, 'y' => 240],
                    'data' => [
                        'label' => 'On Keyword',
                        'type' => 'keyword_trigger',
                        'keywords' => [
                            ['id' => 'kw1', 'value' => 'hello', 'matchType' => 'contains'],
                            ['id' => 'kw2', 'value' => 'appointment', 'matchType' => 'exact'],
                        ],
                    ],
                ],
                [
                    'id' => 'list_message-1',
                    'type' => 'list_message',
                    'position' => ['x' => 380, 'y' => 80],
                    'data' => [
                        'label' => 'Services menu',
                        'type' => 'list_message',
                        'settings' => [
                            'header' => 'HealthCare Clinic',
                            'body' => 'Welcome! Choose a service below.',
                            'footer' => 'Reply *appointment* to book directly.',
                            'buttonText' => 'View services',
                            'sections' => [
                                [
                                    'id' => 'section1',
                                    'title' => 'Services',
                                    'rows' => [
                                        ['id' => 'row1', 'title' => 'Book appointment', 'description' => 'Schedule a visit'],
                                        ['id' => 'row2', 'title' => 'Lab tests', 'description' => 'Order or enquire'],
                                        ['id' => 'row3', 'title' => 'Pharmacy', 'description' => 'Prescriptions & refills'],
                                    ],
                                ],
                                [
                                    'id' => 'section2',
                                    'title' => 'Support',
                                    'rows' => [
                                        ['id' => 'row1', 'title' => 'Talk to doctor', 'description' => 'Medical advice line'],
                                        ['id' => 'row2', 'title' => 'Billing', 'description' => 'Invoices & payments'],
                                        ['id' => 'row3', 'title' => 'Directions', 'description' => 'Find our clinic'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'quick_replies-1',
                    'type' => 'quick_replies',
                    'position' => ['x' => 380, 'y' => 400],
                    'data' => [
                        'label' => 'Appointment menu',
                        'type' => 'quick_replies',
                        'settings' => [
                            'header' => 'HealthCare Clinic',
                            'body' => 'Welcome to HealthCare Clinic. What do you need?',
                            'footer' => null,
                            'activeButtons' => 3,
                            'button1' => 'Book appointment',
                            'button2' => 'Talk to doctor',
                            'button3' => 'Get results',
                        ],
                    ],
                ],
                [
                    'id' => 'whatsapp_flow-1',
                    'type' => 'whatsapp_flow',
                    'position' => ['x' => 760, 'y' => 280],
                    'data' => [
                        'label' => 'Book Your Visit',
                        'type' => 'whatsapp_flow',
                        'settings' => [
                            'whatsappFlowId' => '',
                            'header' => 'Book Your Visit',
                            'footer' => "We'll confirm within 1 hour",
                            'conditions' => [
                                ['id' => 'cond-dept', 'fieldName' => 'department', 'operator' => 'equals', 'value' => 'selected'],
                                ['id' => 'cond-date', 'fieldName' => 'appointment_date', 'operator' => 'equals', 'value' => 'chosen'],
                                ['id' => 'cond-doctor', 'fieldName' => 'doctor', 'operator' => 'equals', 'value' => 'selected'],
                                ['id' => 'cond-insurance', 'fieldName' => 'insurance', 'operator' => 'equals', 'value' => 'confirmed'],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'message-1',
                    'type' => 'message',
                    'position' => ['x' => 1140, 'y' => 240],
                    'data' => [
                        'label' => 'Booking confirmation',
                        'type' => 'message',
                        'settings' => [
                            'message' => "Your appointment is confirmed! ✅\n\nDepartment: {{department}}\nDate: {{appointment_date}}\nDoctor: {{doctor}}\n\nPlease review the pre-visit instructions attached.",
                        ],
                    ],
                ],
                [
                    'id' => 'pdf-1',
                    'type' => 'pdf',
                    'position' => ['x' => 1520, 'y' => 240],
                    'data' => [
                        'label' => 'Pre-visit instructions',
                        'type' => 'pdf',
                        'settings' => [
                            'pdfUrl' => 'https://your-cdn.example.com/clinic/pre-visit-instructions.pdf',
                        ],
                    ],
                ],
                [
                    'id' => 'question-1',
                    'type' => 'question',
                    'position' => ['x' => 760, 'y' => 720],
                    'data' => [
                        'label' => 'Patient ID',
                        'type' => 'question',
                        'settings' => [
                            'question' => 'Enter your patient ID',
                            'variableName' => 'patient_id',
                        ],
                    ],
                ],
                [
                    'id' => 'datastore-1',
                    'type' => 'datastore',
                    'position' => ['x' => 1140, 'y' => 720],
                    'data' => [
                        'label' => 'Save patient ID',
                        'type' => 'datastore',
                        'settings' => [
                            'variableName' => 'patient_id',
                            'variableValue' => '{{patient_id}}',
                            'dataStore' => [
                                'name' => 'patient_lookup',
                                'type' => 'database',
                                'connectionDetails' => [],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'http-1',
                    'type' => 'http',
                    'position' => ['x' => 1520, 'y' => 720],
                    'data' => [
                        'label' => 'Lab results API',
                        'type' => 'http',
                        'settings' => [
                            'http' => [
                                'method' => 'GET',
                                'url' => 'https://your-lis.example.com/api/results/{{patient_id}}',
                                'headers' => [
                                    ['id' => 'h-auth', 'key' => 'Authorization', 'value' => 'Bearer YOUR_LAB_API_TOKEN'],
                                ],
                                'params' => [],
                                'responseVar' => 'lab_results',
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'assign_agent-1',
                    'type' => 'assign_agent',
                    'position' => ['x' => 1900, 'y' => 720],
                    'data' => [
                        'label' => 'Assign results agent',
                        'type' => 'assign_agent',
                        'settings' => ['agentId' => 'none'],
                    ],
                ],
                [
                    'id' => 'assign_group-1',
                    'type' => 'assign_group',
                    'position' => ['x' => 2280, 'y' => 720],
                    'data' => [
                        'label' => 'Results Team',
                        'type' => 'assign_group',
                        'settings' => ['groupId' => '1', 'action' => 'add'],
                    ],
                ],
                [
                    'id' => 'assign_journey_stage-1',
                    'type' => 'assign_journey_stage',
                    'position' => ['x' => 2660, 'y' => 720],
                    'data' => [
                        'label' => 'Awaiting Review',
                        'type' => 'assign_journey_stage',
                        'settings' => ['journeyId' => '1', 'stageId' => '1'],
                    ],
                ],
                [
                    'id' => 'message-2',
                    'type' => 'message',
                    'position' => ['x' => 3040, 'y' => 720],
                    'data' => [
                        'label' => 'Results queued',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'Thank you. Your lab results request for patient ID {{patient_id}} has been queued. A member of the Results Team will review and reply shortly.',
                        ],
                    ],
                ],
                [
                    'id' => 'message-3',
                    'type' => 'message',
                    'position' => ['x' => 1900, 'y' => 640],
                    'data' => [
                        'label' => 'Triage complete',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'A clinician will follow up if needed. For emergencies, call your local emergency number immediately.',
                        ],
                    ],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 3420, 'y' => 400],
                    'data' => ['label' => 'End', 'type' => 'end'],
                ],
            ],
            'edges' => [
                ['id' => 'e-hello-list', 'source' => 'keyword_trigger-1', 'target' => 'list_message-1', 'sourceHandle' => 'keyword-kw1'],
                ['id' => 'e-appt-qr', 'source' => 'keyword_trigger-1', 'target' => 'quick_replies-1', 'sourceHandle' => 'keyword-kw2'],
                ['id' => 'e-list-end', 'source' => 'list_message-1', 'target' => 'end-1'],
                ['id' => 'e-book-flow', 'source' => 'quick_replies-1', 'target' => 'whatsapp_flow-1', 'sourceHandle' => 'button-1'],
                ['id' => 'e-talk-faq', 'source' => 'quick_replies-1', 'target' => 'triage-faq-question-initial', 'sourceHandle' => 'button-2'],
                ['id' => 'e-results-q', 'source' => 'quick_replies-1', 'target' => 'question-1', 'sourceHandle' => 'button-3'],
                ['id' => 'e-flow-confirm', 'source' => 'whatsapp_flow-1', 'target' => 'message-1', 'sourceHandle' => 'condition_0'],
                ['id' => 'e-confirm-pdf', 'source' => 'message-1', 'target' => 'pdf-1'],
                ['id' => 'e-pdf-end', 'source' => 'pdf-1', 'target' => 'end-1'],
                ['id' => 'e-q-store', 'source' => 'question-1', 'target' => 'datastore-1'],
                ['id' => 'e-store-http', 'source' => 'datastore-1', 'target' => 'http-1'],
                ['id' => 'e-http-agent', 'source' => 'http-1', 'target' => 'assign_agent-1'],
                ['id' => 'e-agent-group', 'source' => 'assign_agent-1', 'target' => 'assign_group-1'],
                ['id' => 'e-group-journey', 'source' => 'assign_group-1', 'target' => 'assign_journey_stage-1'],
                ['id' => 'e-journey-msg', 'source' => 'assign_journey_stage-1', 'target' => 'message-2'],
                ['id' => 'e-results-end', 'source' => 'message-2', 'target' => 'end-1'],
                ['id' => 'e-triage-done-end', 'source' => 'message-3', 'target' => 'end-1'],
            ],
        ], [
            'idPrefix' => 'triage-faq',
            'basePosition' => ['x' => 760, 'y' => 520],
            'questionInitial' => 'Please describe your symptoms or health concern. For emergencies, call your local emergency number immediately.',
            'questionFollowup' => 'Any other symptoms or questions? Reply *done* when finished, *urgent* if this is urgent, or *doctor* / *agent* to speak with a clinician.',
            'systemPrompt' => 'You are a medical triage assistant. Assess urgency, ask one clarifying question at a time, and recommend next steps. Never diagnose. Never repeat the patient question — always provide a helpful response. End with: For emergencies, call your local emergency number immediately.',
            'llmVariableName' => 'triage_reply',
            'llmLabel' => 'Medical triage AI',
            'counterMax' => 3,
            'freeExecutions' => 5,
            'enableVectorSearch' => false,
            'humanKeywords' => ['doctor', 'agent', 'human'],
            'keywordExits' => [
                ['id' => 'cond-urgent', 'keyword' => 'urgent', 'target' => 'message-3'],
            ],
            'doneTarget' => 'message-3',
            'humanTarget' => 'message-3',
            'limitTarget' => 'message-3',
        ]),
    ],

    'real_estate_agency_bot' => [
        'name' => 'Real Estate Agency Bot',
        'description' => 'Buy/rent property menus, AI lead qualification loop, CRM sync, catalog browsing, and commercial inquiries.',
        'category' => 'real_estate',
        'form_bundle' => 'real_estate_inquiry',
        'video_url' => null,
        'setup_hint' => 'Set listing-mode catalog ID on Listing Inquiry, CRM API, commercial WhatsApp Flow, OpenRouter key. Save draft → Publish.',
        'post_install_checklist' => ['Listing catalog ID', 'CRM API URL', 'Hot Leads group', 'OpenRouter key', 'Publish'],
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
                            ['id' => 'kw1', 'value' => 'buy', 'matchType' => 'exact'],
                            ['id' => 'kw2', 'value' => 'rent', 'matchType' => 'contains'],
                        ],
                    ],
                ],
                [
                    'id' => 'list_message-1',
                    'type' => 'list_message',
                    'position' => ['x' => 380, 'y' => 200],
                    'data' => [
                        'label' => 'Property menu',
                        'type' => 'list_message',
                        'settings' => [
                            'header' => 'Property Search',
                            'body' => 'Browse properties for sale or rent.',
                            'footer' => 'Our agents respond within 24 hours.',
                            'buttonText' => 'View listings',
                            'sections' => [
                                [
                                    'id' => 'section1',
                                    'title' => 'Buy Property',
                                    'rows' => [
                                        ['id' => 'row1', 'title' => 'Apartments', 'description' => 'Flats & condos for sale'],
                                        ['id' => 'row2', 'title' => 'Houses', 'description' => 'Standalone homes'],
                                        ['id' => 'row3', 'title' => 'Commercial', 'description' => 'Offices & retail space'],
                                    ],
                                ],
                                [
                                    'id' => 'section2',
                                    'title' => 'Rent Property',
                                    'rows' => [
                                        ['id' => 'row1', 'title' => 'Studio', 'description' => 'Compact rentals'],
                                        ['id' => 'row2', 'title' => '1 Bedroom', 'description' => '1-bed units'],
                                        ['id' => 'row3', 'title' => '2+ Bedrooms', 'description' => 'Family-sized rentals'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'question-1',
                    'type' => 'question',
                    'position' => ['x' => 1140, 'y' => 80],
                    'data' => [
                        'label' => 'Viewing intent',
                        'type' => 'question',
                        'settings' => [
                            'question' => 'Would you like us to schedule a viewing? Reply YES or NO.',
                            'variableName' => 'viewing_intent',
                        ],
                    ],
                ],
                [
                    'id' => 'datastore-1',
                    'type' => 'datastore',
                    'position' => ['x' => 1520, 'y' => 80],
                    'data' => [
                        'label' => 'Save lead data',
                        'type' => 'datastore',
                        'settings' => [
                            'variableName' => 'viewing_intent',
                            'variableValue' => '{{viewing_intent}} | Summary: {{buy_lead_summary}}',
                            'dataStore' => [
                                'name' => 'real_estate_lead',
                                'type' => 'database',
                                'connectionDetails' => [],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'http-1',
                    'type' => 'http',
                    'position' => ['x' => 1900, 'y' => 80],
                    'data' => [
                        'label' => 'CRM API',
                        'type' => 'http',
                        'settings' => [
                            'http' => [
                                'method' => 'POST',
                                'url' => 'https://your-crm.example.com/api/leads',
                                'headers' => [
                                    ['id' => 'h-json', 'key' => 'Content-Type', 'value' => 'application/json'],
                                    ['id' => 'h-auth', 'key' => 'Authorization', 'value' => 'Bearer YOUR_CRM_TOKEN'],
                                ],
                                'params' => [
                                    ['id' => 'p-name', 'key' => 'name', 'value' => '{{contact_name}}'],
                                    ['id' => 'p-phone', 'key' => 'phone', 'value' => '{{contact_phone}}'],
                                    ['id' => 'p-intent', 'key' => 'viewing_intent', 'value' => '{{viewing_intent}}'],
                                    ['id' => 'p-summary', 'key' => 'notes', 'value' => '{{buy_lead_summary}}'],
                                ],
                                'responseVar' => 'crm_lead_result',
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'message-1',
                    'type' => 'message',
                    'position' => ['x' => 2280, 'y' => 80],
                    'data' => [
                        'label' => 'Lead received',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'Your request has been received. An agent will contact you shortly.',
                        ],
                    ],
                ],
                [
                    'id' => 'assign_agent-1',
                    'type' => 'assign_agent',
                    'position' => ['x' => 2660, 'y' => 80],
                    'data' => [
                        'label' => 'Assign agent',
                        'type' => 'assign_agent',
                        'settings' => ['agentId' => 'none'],
                    ],
                ],
                [
                    'id' => 'assign_group-1',
                    'type' => 'assign_group',
                    'position' => ['x' => 3040, 'y' => 80],
                    'data' => [
                        'label' => 'Hot Leads',
                        'type' => 'assign_group',
                        'settings' => ['groupId' => '1', 'action' => 'add'],
                    ],
                ],
                [
                    'id' => 'listing_inquiry-1',
                    'type' => 'listing_inquiry',
                    'position' => ['x' => 760, 'y' => 360],
                    'data' => [
                        'label' => 'Browse rentals',
                        'type' => 'listing_inquiry',
                        'settings' => [
                            'catalogId' => '',
                            'header' => 'Available rentals',
                            'footer' => 'Book a viewing on WhatsApp.',
                            'completionType' => 'booking',
                            'bookingVariablePrefix' => 'listing_booking',
                            'requirePreferredDateTime' => true,
                            'bookingBackend' => 'whatsapp_only',
                        ],
                    ],
                ],
                [
                    'id' => 'branch-1',
                    'type' => 'branch',
                    'position' => ['x' => 1520, 'y' => 360],
                    'data' => [
                        'label' => 'Price inquiry',
                        'type' => 'branch',
                        'settings' => [
                            'webhookVariables' => [],
                            'conditions' => [
                                [
                                    'id' => 'cond-price',
                                    'nodeId' => 'branch-1',
                                    'variableId' => 'contact_last_message',
                                    'operator' => 'contains',
                                    'value' => 'price',
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'counter-1',
                    'type' => 'counter',
                    'position' => ['x' => 1900, 'y' => 320],
                    'data' => [
                        'label' => 'Video limit',
                        'type' => 'counter',
                        'settings' => [
                            'counter' => ['maxExecutions' => 3, 'period' => 'all_time'],
                        ],
                    ],
                ],
                [
                    'id' => 'check_pricing-1',
                    'type' => 'check_pricing',
                    'position' => ['x' => 2280, 'y' => 320],
                    'data' => [
                        'label' => 'Check credits',
                        'type' => 'check_pricing',
                        'settings' => ['pricing' => ['freeExecutions' => 5]],
                    ],
                ],
                [
                    'id' => 'video-1',
                    'type' => 'video',
                    'position' => ['x' => 2660, 'y' => 320],
                    'data' => [
                        'label' => 'Property walkthrough',
                        'type' => 'video',
                        'settings' => [
                            'videoUrl' => 'https://your-cdn.example.com/realestate/property-walkthrough.mp4',
                        ],
                    ],
                ],
                [
                    'id' => 'whatsapp_flow-1',
                    'type' => 'whatsapp_flow',
                    'position' => ['x' => 760, 'y' => 560],
                    'data' => [
                        'label' => 'Commercial inquiry',
                        'type' => 'whatsapp_flow',
                        'settings' => [
                            'whatsappFlowId' => '',
                            'header' => 'Commercial Property Inquiry',
                            'footer' => 'Our team responds within 24 hours',
                            'conditions' => [
                                ['id' => 'cond-type', 'fieldName' => 'property_type', 'operator' => 'equals', 'value' => 'submitted'],
                                ['id' => 'cond-budget', 'fieldName' => 'budget_range', 'operator' => 'equals', 'value' => 'submitted'],
                                ['id' => 'cond-lease', 'fieldName' => 'lease_duration', 'operator' => 'equals', 'value' => 'submitted'],
                                ['id' => 'cond-size', 'fieldName' => 'company_size', 'operator' => 'equals', 'value' => 'submitted'],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'message-2',
                    'type' => 'message',
                    'position' => ['x' => 1140, 'y' => 560],
                    'data' => [
                        'label' => 'Commercial received',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'Thank you for your commercial property inquiry. A specialist will contact you within 24 hours.',
                        ],
                    ],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 3420, 'y' => 320],
                    'data' => ['label' => 'End', 'type' => 'end'],
                ],
            ],
            'edges' => [
                ['id' => 'e-buy-list', 'source' => 'keyword_trigger-1', 'target' => 'list_message-1', 'sourceHandle' => 'keyword-kw1'],
                ['id' => 'e-rent-list', 'source' => 'keyword_trigger-1', 'target' => 'list_message-1', 'sourceHandle' => 'keyword-kw2'],
                ['id' => 'e-apt-faq', 'source' => 'list_message-1', 'target' => 'buy-faq-question-initial', 'sourceHandle' => 'section1-row1'],
                ['id' => 'e-commercial-flow', 'source' => 'list_message-1', 'target' => 'whatsapp_flow-1', 'sourceHandle' => 'section1-row3'],
                ['id' => 'e-studio-listing', 'source' => 'list_message-1', 'target' => 'listing_inquiry-1', 'sourceHandle' => 'section2-row1'],
                ['id' => 'e-listing-group', 'source' => 'listing_inquiry-1', 'target' => 'assign_group-1', 'sourceHandle' => 'onListingInquiry'],
                ['id' => 'e-houses-branch', 'source' => 'list_message-1', 'target' => 'branch-1', 'sourceHandle' => 'section1-row2'],
                ['id' => 'e-price-counter', 'source' => 'branch-1', 'target' => 'counter-1', 'sourceHandle' => 'condition-cond-price-true'],
                ['id' => 'e-branch-false-end', 'source' => 'branch-1', 'target' => 'end-1', 'sourceHandle' => 'condition-cond-price-false'],
                ['id' => 'e-counter-pricing', 'source' => 'counter-1', 'target' => 'check_pricing-1', 'sourceHandle' => 'true'],
                ['id' => 'e-counter-false-end', 'source' => 'counter-1', 'target' => 'end-1', 'sourceHandle' => 'false'],
                ['id' => 'e-pricing-video', 'source' => 'check_pricing-1', 'target' => 'video-1', 'sourceHandle' => 'true'],
                ['id' => 'e-pricing-false-end', 'source' => 'check_pricing-1', 'target' => 'end-1', 'sourceHandle' => 'false'],
                ['id' => 'e-video-end', 'source' => 'video-1', 'target' => 'end-1'],
                ['id' => 'e-q-store', 'source' => 'question-1', 'target' => 'datastore-1'],
                ['id' => 'e-store-http', 'source' => 'datastore-1', 'target' => 'http-1'],
                ['id' => 'e-http-msg', 'source' => 'http-1', 'target' => 'message-1'],
                ['id' => 'e-msg-agent', 'source' => 'message-1', 'target' => 'assign_agent-1'],
                ['id' => 'e-agent-group', 'source' => 'assign_agent-1', 'target' => 'assign_group-1'],
                ['id' => 'e-group-end', 'source' => 'assign_group-1', 'target' => 'end-1'],
                ['id' => 'e-flow-msg', 'source' => 'whatsapp_flow-1', 'target' => 'message-2', 'sourceHandle' => 'onFlowCompleted'],
                ['id' => 'e-commercial-end', 'source' => 'message-2', 'target' => 'end-1'],
            ],
        ], [
            'idPrefix' => 'buy-faq',
            'basePosition' => ['x' => 760, 'y' => 80],
            'questionInitial' => 'Tell me about the property you are looking for — budget, location, bedrooms, and timeline.',
            'questionFollowup' => 'Anything else to add? Reply *done* when ready to schedule a viewing, or *agent* to speak with an advisor now.',
            'systemPrompt' => 'You are a real estate assistant. Help the client clarify budget, preferred location, number of bedrooms, and timeline. Be warm and professional. Never repeat the customer question — always provide a helpful response.',
            'llmVariableName' => 'buy_lead_summary',
            'llmLabel' => 'Buy assistant AI',
            'counterMax' => 6,
            'enableVectorSearch' => false,
            'doneTarget' => 'question-1',
            'humanTarget' => 'assign_agent-1',
        ]),
    ],

    'microfinance_banking_bot' => [
        'name' => 'Microfinance Banking Bot',
        'description' => 'Balance checks, loan applications, repayments via M-Pesa, and AI-formatted loan status updates.',
        'category' => 'banking',
        'form_bundle' => 'microfinance_loan_application',
        'video_url' => null,
        'setup_hint' => 'Configure banking API, M-Pesa, loan template, Repayments group. LLM auto-sends loan status (no duplicate message). Save draft → Publish.',
        'post_install_checklist' => ['Banking API URLs', 'M-Pesa credentials', 'Repayments group', 'Publish'],
        'flow_data' => [
            'nodes' => [
                [
                    'id' => 'keyword_trigger-1',
                    'type' => 'keyword_trigger',
                    'position' => ['x' => 0, 'y' => 240],
                    'data' => [
                        'label' => 'On Keyword',
                        'type' => 'keyword_trigger',
                        'keywords' => [
                            ['id' => 'kw1', 'value' => 'balance', 'matchType' => 'exact'],
                            ['id' => 'kw2', 'value' => 'loan', 'matchType' => 'contains'],
                        ],
                    ],
                ],
                [
                    'id' => 'incomingMessage-1',
                    'type' => 'incomingMessage',
                    'position' => ['x' => 380, 'y' => 80],
                    'data' => [
                        'label' => 'Balance inquiry',
                        'type' => 'incomingMessage',
                        'settings' => [],
                    ],
                ],
                [
                    'id' => 'branch-1',
                    'type' => 'branch',
                    'position' => ['x' => 760, 'y' => 80],
                    'data' => [
                        'label' => 'Account verified',
                        'type' => 'branch',
                        'settings' => [
                            'webhookVariables' => [],
                            'conditions' => [
                                [
                                    'id' => 'cond-verified',
                                    'nodeId' => 'branch-1',
                                    'variableId' => 'account',
                                    'operator' => 'equals',
                                    'value' => 'verified',
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'counter-1',
                    'type' => 'counter',
                    'position' => ['x' => 1140, 'y' => 40],
                    'data' => [
                        'label' => 'Balance check limit',
                        'type' => 'counter',
                        'settings' => [
                            'counter' => ['maxExecutions' => 5, 'period' => 'last_30_days'],
                        ],
                    ],
                ],
                [
                    'id' => 'check_pricing-1',
                    'type' => 'check_pricing',
                    'position' => ['x' => 1520, 'y' => 40],
                    'data' => [
                        'label' => 'Check credits',
                        'type' => 'check_pricing',
                        'settings' => ['pricing' => ['freeExecutions' => 10]],
                    ],
                ],
                [
                    'id' => 'http-1',
                    'type' => 'http',
                    'position' => ['x' => 1900, 'y' => 40],
                    'data' => [
                        'label' => 'Core banking API',
                        'type' => 'http',
                        'settings' => [
                            'http' => [
                                'method' => 'GET',
                                'url' => 'https://your-core-banking.example.com/api/accounts/{{contact_phone}}/balance',
                                'headers' => [
                                    ['id' => 'h-auth', 'key' => 'Authorization', 'value' => 'Bearer YOUR_BANKING_API_TOKEN'],
                                ],
                                'params' => [],
                                'responseVar' => 'account_balance',
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'message-1',
                    'type' => 'message',
                    'position' => ['x' => 2280, 'y' => 40],
                    'data' => [
                        'label' => 'Balance summary',
                        'type' => 'message',
                        'settings' => [
                            'message' => "Your account balance:\n\n{{account_balance}}\n\nThank you for banking with us.",
                        ],
                    ],
                ],
                [
                    'id' => 'quick_replies-1',
                    'type' => 'quick_replies',
                    'position' => ['x' => 380, 'y' => 400],
                    'data' => [
                        'label' => 'Loan services',
                        'type' => 'quick_replies',
                        'settings' => [
                            'header' => 'Loan Services',
                            'body' => 'Welcome to Loan Services. Choose an option:',
                            'footer' => null,
                            'activeButtons' => 3,
                            'button1' => 'Apply for loan',
                            'button2' => 'Check loan status',
                            'button3' => 'Repay loan',
                        ],
                    ],
                ],
                [
                    'id' => 'whatsapp_flow-1',
                    'type' => 'whatsapp_flow',
                    'position' => ['x' => 760, 'y' => 320],
                    'data' => [
                        'label' => 'Loan Application',
                        'type' => 'whatsapp_flow',
                        'settings' => [
                            'whatsappFlowId' => '',
                            'header' => 'Loan Application',
                            'footer' => 'Decision within 30 minutes',
                            'conditions' => [
                                ['id' => 'cond-personal', 'fieldName' => 'personal_details', 'operator' => 'equals', 'value' => 'submitted'],
                                ['id' => 'cond-employment', 'fieldName' => 'employment_info', 'operator' => 'equals', 'value' => 'submitted'],
                                ['id' => 'cond-amount', 'fieldName' => 'loan_amount', 'operator' => 'equals', 'value' => 'selected'],
                                ['id' => 'cond-terms', 'fieldName' => 'terms', 'operator' => 'equals', 'value' => 'accepted'],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'template-1',
                    'type' => 'template',
                    'position' => ['x' => 1140, 'y' => 320],
                    'data' => [
                        'label' => 'Loan offer template',
                        'type' => 'template',
                        'settings' => [
                            'selectedTemplateId' => '',
                            'parameters' => [
                                'customer_name' => '{{contact_name}}',
                                'loan_amount' => '{{loan_amount}}',
                                'repayment_period' => '{{repayment_period}}',
                            ],
                            'fileUrl' => null,
                            'videoUrl' => null,
                        ],
                    ],
                ],
                [
                    'id' => 'question-1',
                    'type' => 'question',
                    'position' => ['x' => 760, 'y' => 520],
                    'data' => [
                        'label' => 'Loan account number',
                        'type' => 'question',
                        'settings' => [
                            'question' => 'Enter your loan account number.',
                            'variableName' => 'loan_account',
                        ],
                    ],
                ],
                [
                    'id' => 'datastore-1',
                    'type' => 'datastore',
                    'position' => ['x' => 1140, 'y' => 520],
                    'data' => [
                        'label' => 'Save loan account',
                        'type' => 'datastore',
                        'settings' => [
                            'variableName' => 'loan_account',
                            'variableValue' => '{{loan_account}}',
                            'dataStore' => [
                                'name' => 'loan_repayment',
                                'type' => 'database',
                                'connectionDetails' => [],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'http-2',
                    'type' => 'http',
                    'position' => ['x' => 1520, 'y' => 520],
                    'data' => [
                        'label' => 'Verify loan account',
                        'type' => 'http',
                        'settings' => [
                            'http' => [
                                'method' => 'GET',
                                'url' => 'https://your-core-banking.example.com/api/loans/{{loan_account}}/verify',
                                'headers' => [
                                    ['id' => 'h-auth', 'key' => 'Authorization', 'value' => 'Bearer YOUR_BANKING_API_TOKEN'],
                                ],
                                'params' => [],
                                'responseVar' => 'loan_verify_result',
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'mpesa_stk_push-1',
                    'type' => 'mpesa_stk_push',
                    'position' => ['x' => 1900, 'y' => 520],
                    'data' => [
                        'label' => 'Collect repayment',
                        'type' => 'mpesa_stk_push',
                        'settings' => [
                            'mpesa' => [
                                'amount' => '{{repayment_amount}}',
                                'accountReference' => 'LOAN-REPAY',
                                'transactionDesc' => 'Loan repayment',
                                'responseVar' => 'repayment_result',
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'message-2',
                    'type' => 'message',
                    'position' => ['x' => 2280, 'y' => 480],
                    'data' => [
                        'label' => 'Payment receipt',
                        'type' => 'message',
                        'settings' => [
                            'message' => "Payment received ✅\n\nAccount: {{loan_account}}\nReference: {{repayment_result}}\n\nThank you for your repayment.",
                        ],
                    ],
                ],
                [
                    'id' => 'assign_group-1',
                    'type' => 'assign_group',
                    'position' => ['x' => 2660, 'y' => 480],
                    'data' => [
                        'label' => 'Repayments',
                        'type' => 'assign_group',
                        'settings' => ['groupId' => '1', 'action' => 'add'],
                    ],
                ],
                [
                    'id' => 'assign_journey_stage-1',
                    'type' => 'assign_journey_stage',
                    'position' => ['x' => 3040, 'y' => 480],
                    'data' => [
                        'label' => 'Active Repayers',
                        'type' => 'assign_journey_stage',
                        'settings' => ['journeyId' => '1', 'stageId' => '1'],
                    ],
                ],
                [
                    'id' => 'question-2',
                    'type' => 'question',
                    'position' => ['x' => 760, 'y' => 720],
                    'data' => [
                        'label' => 'Status lookup ID',
                        'type' => 'question',
                        'settings' => [
                            'question' => 'Enter your loan reference or account number.',
                            'variableName' => 'loan_status_ref',
                        ],
                    ],
                ],
                [
                    'id' => 'datastore-2',
                    'type' => 'datastore',
                    'position' => ['x' => 1140, 'y' => 720],
                    'data' => [
                        'label' => 'Save status ref',
                        'type' => 'datastore',
                        'settings' => [
                            'variableName' => 'loan_status_ref',
                            'variableValue' => '{{loan_status_ref}}',
                            'dataStore' => [
                                'name' => 'loan_status_lookup',
                                'type' => 'database',
                                'connectionDetails' => [],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'http-3',
                    'type' => 'http',
                    'position' => ['x' => 1520, 'y' => 720],
                    'data' => [
                        'label' => 'Fetch loan status',
                        'type' => 'http',
                        'settings' => [
                            'http' => [
                                'method' => 'GET',
                                'url' => 'https://your-core-banking.example.com/api/loans/{{loan_status_ref}}/status',
                                'headers' => [
                                    ['id' => 'h-auth', 'key' => 'Authorization', 'value' => 'Bearer YOUR_BANKING_API_TOKEN'],
                                ],
                                'params' => [],
                                'responseVar' => 'loan_status_raw',
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'openai-1',
                    'type' => 'openai',
                    'position' => ['x' => 1900, 'y' => 720],
                    'data' => [
                        'label' => 'Format loan status',
                        'type' => 'openai',
                        'settings' => [
                            'llm' => [
                                'model' => 'openai/gpt-4o-mini',
                                'systemPrompt' => 'You are a loan officer assistant. Present loan status information clearly and empathetically. Highlight next payment due date.',
                                'prompt' => 'Loan status data: {{loan_status_raw}}',
                                'temperature' => 0.4,
                                'maxTokens' => 500,
                                'variableName' => 'loan_status_reply',
                                'autoSendMessage' => true,
                                'enableVectorSearch' => false,
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
                    'position' => ['x' => 2280, 'y' => 720],
                    'data' => [
                        'label' => 'Status reply',
                        'type' => 'message',
                        'settings' => [
                            'message' => '{{loan_status_reply}}',
                        ],
                    ],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 3420, 'y' => 400],
                    'data' => ['label' => 'End', 'type' => 'end'],
                ],
            ],
            'edges' => [
                ['id' => 'e-balance-incoming', 'source' => 'keyword_trigger-1', 'target' => 'incomingMessage-1', 'sourceHandle' => 'keyword-kw1'],
                ['id' => 'e-loan-qr', 'source' => 'keyword_trigger-1', 'target' => 'quick_replies-1', 'sourceHandle' => 'keyword-kw2'],
                ['id' => 'e-incoming-branch', 'source' => 'incomingMessage-1', 'target' => 'branch-1'],
                ['id' => 'e-verified-counter', 'source' => 'branch-1', 'target' => 'counter-1', 'sourceHandle' => 'condition-cond-verified-true'],
                ['id' => 'e-branch-false-end', 'source' => 'branch-1', 'target' => 'end-1', 'sourceHandle' => 'condition-cond-verified-false'],
                ['id' => 'e-counter-pricing', 'source' => 'counter-1', 'target' => 'check_pricing-1', 'sourceHandle' => 'true'],
                ['id' => 'e-counter-false-end', 'source' => 'counter-1', 'target' => 'end-1', 'sourceHandle' => 'false'],
                ['id' => 'e-pricing-http', 'source' => 'check_pricing-1', 'target' => 'http-1', 'sourceHandle' => 'true'],
                ['id' => 'e-pricing-false-end', 'source' => 'check_pricing-1', 'target' => 'end-1', 'sourceHandle' => 'false'],
                ['id' => 'e-http-balance', 'source' => 'http-1', 'target' => 'message-1'],
                ['id' => 'e-balance-end', 'source' => 'message-1', 'target' => 'end-1'],
                ['id' => 'e-apply-flow', 'source' => 'quick_replies-1', 'target' => 'whatsapp_flow-1', 'sourceHandle' => 'button-1'],
                ['id' => 'e-status-q', 'source' => 'quick_replies-1', 'target' => 'question-2', 'sourceHandle' => 'button-2'],
                ['id' => 'e-repay-q', 'source' => 'quick_replies-1', 'target' => 'question-1', 'sourceHandle' => 'button-3'],
                ['id' => 'e-flow-template', 'source' => 'whatsapp_flow-1', 'target' => 'template-1', 'sourceHandle' => 'condition_3'],
                ['id' => 'e-template-end', 'source' => 'template-1', 'target' => 'end-1'],
                ['id' => 'e-q-store', 'source' => 'question-1', 'target' => 'datastore-1'],
                ['id' => 'e-store-verify', 'source' => 'datastore-1', 'target' => 'http-2'],
                ['id' => 'e-verify-mpesa', 'source' => 'http-2', 'target' => 'mpesa_stk_push-1'],
                ['id' => 'e-mpesa-success', 'source' => 'mpesa_stk_push-1', 'target' => 'message-2', 'sourceHandle' => 'mpesa-success'],
                ['id' => 'e-mpesa-failed-end', 'source' => 'mpesa_stk_push-1', 'target' => 'end-1', 'sourceHandle' => 'mpesa-failed'],
                ['id' => 'e-receipt-group', 'source' => 'message-2', 'target' => 'assign_group-1'],
                ['id' => 'e-group-journey', 'source' => 'assign_group-1', 'target' => 'assign_journey_stage-1'],
                ['id' => 'e-journey-end', 'source' => 'assign_journey_stage-1', 'target' => 'end-1'],
                ['id' => 'e-status-store', 'source' => 'question-2', 'target' => 'datastore-2'],
                ['id' => 'e-store-status-http', 'source' => 'datastore-2', 'target' => 'http-3'],
                ['id' => 'e-http-openai', 'source' => 'http-3', 'target' => 'openai-1'],
                ['id' => 'e-openai-end', 'source' => 'openai-1', 'target' => 'end-1'],
                ['id' => 'e-status-end', 'source' => 'message-3', 'target' => 'end-1'],
            ],
        ],
    ],

    'hotel_tour_concierge_bot' => [
        'name' => 'Hotel & Tour Concierge Bot',
        'description' => 'Room bookings, honeymoon packages, safari tours, custom itineraries, and booking confirmations.',
        'category' => 'hospitality',
        'form_bundle' => 'hospitality_booking',
        'video_url' => null,
        'setup_hint' => 'Link booking WhatsApp Flows, listing catalog, M-Pesa deposits, VIP group. FAQ paths use multi-turn AI loops. Save draft → Publish.',
        'post_install_checklist' => ['Suite WhatsApp Flow ID', 'Safari listing catalog ID', 'M-Pesa deposit settings', 'VIP Guests group', 'Publish'],
        'flow_data' => FaqConversationLoop::mergeInto(
            FaqConversationLoop::mergeInto([
                'nodes' => [
                    [
                        'id' => 'keyword_trigger-1',
                        'type' => 'keyword_trigger',
                        'position' => ['x' => 0, 'y' => 240],
                        'data' => [
                            'label' => 'On Keyword',
                            'type' => 'keyword_trigger',
                            'keywords' => [
                                ['id' => 'kw1', 'value' => 'book', 'matchType' => 'exact'],
                                ['id' => 'kw2', 'value' => 'tour', 'matchType' => 'contains'],
                            ],
                        ],
                    ],
                    [
                        'id' => 'list_message-1',
                        'type' => 'list_message',
                        'position' => ['x' => 380, 'y' => 120],
                        'data' => [
                            'label' => 'Accommodation menu',
                            'type' => 'list_message',
                            'settings' => [
                                'header' => 'Book Your Stay',
                                'body' => 'Choose accommodation or a package.',
                                'footer' => 'Reply *tour* to explore experiences.',
                                'buttonText' => 'View options',
                                'sections' => [
                                    [
                                        'id' => 'section1',
                                        'title' => 'Accommodation',
                                        'rows' => [
                                            ['id' => 'row1', 'title' => 'Standard Room', 'description' => 'Comfortable essentials'],
                                            ['id' => 'row2', 'title' => 'Deluxe Room', 'description' => 'Upgraded amenities'],
                                            ['id' => 'row3', 'title' => 'Suite', 'description' => 'Premium suite experience'],
                                        ],
                                    ],
                                    [
                                        'id' => 'section2',
                                        'title' => 'Packages',
                                        'rows' => [
                                            ['id' => 'row1', 'title' => 'Honeymoon Package', 'description' => 'Romantic getaway'],
                                            ['id' => 'row2', 'title' => 'Family Package', 'description' => 'Fun for all ages'],
                                            ['id' => 'row3', 'title' => 'Business Stay', 'description' => 'Work-friendly rates'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => 'whatsapp_flow-1',
                        'type' => 'whatsapp_flow',
                        'position' => ['x' => 760, 'y' => 80],
                        'data' => [
                            'label' => 'Suite Reservation',
                            'type' => 'whatsapp_flow',
                            'settings' => [
                                'whatsappFlowId' => '',
                                'header' => 'Suite Reservation',
                                'footer' => 'Complimentary airport transfer included',
                                'conditions' => [
                                    ['id' => 'cond-checkin', 'fieldName' => 'check_in_date', 'operator' => 'equals', 'value' => 'confirmed'],
                                    ['id' => 'cond-checkout', 'fieldName' => 'check_out_date', 'operator' => 'equals', 'value' => 'confirmed'],
                                    ['id' => 'cond-guests', 'fieldName' => 'guest_count', 'operator' => 'equals', 'value' => 'confirmed'],
                                    ['id' => 'cond-requests', 'fieldName' => 'special_requests', 'operator' => 'equals', 'value' => 'submitted'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => 'image-1',
                        'type' => 'image',
                        'position' => ['x' => 1140, 'y' => 40],
                        'data' => [
                            'label' => 'Suite photos',
                            'type' => 'image',
                            'settings' => [
                                'imageUrl' => 'https://your-cdn.example.com/hotel/suite-gallery.jpg',
                            ],
                        ],
                    ],
                    [
                        'id' => 'pdf-1',
                        'type' => 'pdf',
                        'position' => ['x' => 1520, 'y' => 40],
                        'data' => [
                            'label' => 'Amenities guide',
                            'type' => 'pdf',
                            'settings' => [
                                'pdfUrl' => 'https://your-cdn.example.com/hotel/suite-amenities-guide.pdf',
                            ],
                        ],
                    ],
                    [
                        'id' => 'question-1',
                        'type' => 'question',
                        'position' => ['x' => 1140, 'y' => 280],
                        'data' => [
                            'label' => 'Guest count',
                            'type' => 'question',
                            'settings' => [
                                'question' => 'Enter the number of guests',
                                'variableName' => 'guest_count',
                            ],
                        ],
                    ],
                    [
                        'id' => 'datastore-1',
                        'type' => 'datastore',
                        'position' => ['x' => 1520, 'y' => 280],
                        'data' => [
                            'label' => 'Save booking data',
                            'type' => 'datastore',
                            'settings' => [
                                'variableName' => 'guest_count',
                                'variableValue' => '{{guest_count}} | Package: Honeymoon | Details: {{honeymoon_details}}',
                                'dataStore' => [
                                    'name' => 'hotel_booking',
                                    'type' => 'database',
                                    'connectionDetails' => [],
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => 'http-1',
                        'type' => 'http',
                        'position' => ['x' => 1900, 'y' => 280],
                        'data' => [
                            'label' => 'Booking API',
                            'type' => 'http',
                            'settings' => [
                                'http' => [
                                    'method' => 'POST',
                                    'url' => 'https://your-pms.example.com/api/bookings',
                                    'headers' => [
                                        ['id' => 'h-json', 'key' => 'Content-Type', 'value' => 'application/json'],
                                        ['id' => 'h-auth', 'key' => 'Authorization', 'value' => 'Bearer YOUR_BOOKING_API_TOKEN'],
                                    ],
                                    'params' => [
                                        ['id' => 'p-name', 'key' => 'guest_name', 'value' => '{{contact_name}}'],
                                        ['id' => 'p-phone', 'key' => 'phone', 'value' => '{{contact_phone}}'],
                                        ['id' => 'p-guests', 'key' => 'guest_count', 'value' => '{{guest_count}}'],
                                        ['id' => 'p-package', 'key' => 'package', 'value' => 'honeymoon'],
                                        ['id' => 'p-notes', 'key' => 'notes', 'value' => '{{honeymoon_details}}'],
                                    ],
                                    'responseVar' => 'booking_result',
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => 'mpesa_stk_push-1',
                        'type' => 'mpesa_stk_push',
                        'position' => ['x' => 2280, 'y' => 280],
                        'data' => [
                            'label' => '30% deposit',
                            'type' => 'mpesa_stk_push',
                            'settings' => [
                                'mpesa' => [
                                    'amount' => '{{deposit_amount}}',
                                    'accountReference' => 'HOTEL-DEPOSIT',
                                    'transactionDesc' => 'Booking deposit (30%)',
                                    'responseVar' => 'deposit_result',
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => 'assign_group-1',
                        'type' => 'assign_group',
                        'position' => ['x' => 2660, 'y' => 240],
                        'data' => [
                            'label' => 'VIP Guests',
                            'type' => 'assign_group',
                            'settings' => ['groupId' => '1', 'action' => 'add'],
                        ],
                    ],
                    [
                        'id' => 'assign_journey_stage-1',
                        'type' => 'assign_journey_stage',
                        'position' => ['x' => 3040, 'y' => 240],
                        'data' => [
                            'label' => 'Booking Confirmed',
                            'type' => 'assign_journey_stage',
                            'settings' => ['journeyId' => '1', 'stageId' => '1'],
                        ],
                    ],
                    [
                        'id' => 'template-1',
                        'type' => 'template',
                        'position' => ['x' => 3420, 'y' => 240],
                        'data' => [
                            'label' => 'Booking confirmation',
                            'type' => 'template',
                            'settings' => [
                                'selectedTemplateId' => '',
                                'parameters' => [
                                    'guest_name' => '{{contact_name}}',
                                    'booking_reference' => '{{booking_result.reference}}',
                                    'check_in_date' => '{{check_in_date}}',
                                ],
                                'fileUrl' => null,
                                'videoUrl' => null,
                            ],
                        ],
                    ],
                    [
                        'id' => 'quick_replies-1',
                        'type' => 'quick_replies',
                        'position' => ['x' => 380, 'y' => 520],
                        'data' => [
                            'label' => 'Tour menu',
                            'type' => 'quick_replies',
                            'settings' => [
                                'header' => 'Tours & Experiences',
                                'body' => 'Explore our experiences!',
                                'footer' => null,
                                'activeButtons' => 3,
                                'button1' => 'Safari tours',
                                'button2' => 'City tours',
                                'button3' => 'Custom itinerary',
                            ],
                        ],
                    ],
                    [
                        'id' => 'video-1',
                        'type' => 'video',
                        'position' => ['x' => 760, 'y' => 480],
                        'data' => [
                            'label' => 'Safari highlights',
                            'type' => 'video',
                            'settings' => [
                                'videoUrl' => 'https://your-cdn.example.com/tours/safari-highlights.mp4',
                            ],
                        ],
                    ],
                    [
                        'id' => 'listing_inquiry-2',
                        'type' => 'listing_inquiry',
                        'position' => ['x' => 1140, 'y' => 480],
                        'data' => [
                            'label' => 'Safari packages',
                            'type' => 'listing_inquiry',
                            'settings' => [
                                'catalogId' => '',
                                'header' => 'Safari packages',
                                'footer' => 'Book a safari on WhatsApp.',
                                'completionType' => 'booking',
                                'bookingVariablePrefix' => 'safari_booking',
                                'requirePreferredDateTime' => true,
                                'bookingBackend' => 'whatsapp_only',
                            ],
                        ],
                    ],
                    [
                        'id' => 'message-2',
                        'type' => 'message',
                        'position' => ['x' => 1900, 'y' => 480],
                        'data' => [
                            'label' => 'City tours info',
                            'type' => 'message',
                            'settings' => [
                                'message' => 'Our city tours run daily at 9 AM and 2 PM. Reply *book* to reserve or ask about private guides.',
                            ],
                        ],
                    ],
                    [
                        'id' => 'end-1',
                        'type' => 'end',
                        'position' => ['x' => 3800, 'y' => 400],
                        'data' => ['label' => 'End', 'type' => 'end'],
                    ],
                ],
                'edges' => [
                    ['id' => 'e-book-list', 'source' => 'keyword_trigger-1', 'target' => 'list_message-1', 'sourceHandle' => 'keyword-kw1'],
                    ['id' => 'e-tour-qr', 'source' => 'keyword_trigger-1', 'target' => 'quick_replies-1', 'sourceHandle' => 'keyword-kw2'],
                    ['id' => 'e-suite-flow', 'source' => 'list_message-1', 'target' => 'whatsapp_flow-1', 'sourceHandle' => 'section1-row3'],
                    ['id' => 'e-honeymoon-faq', 'source' => 'list_message-1', 'target' => 'honeymoon-faq-question-initial', 'sourceHandle' => 'section2-row1'],
                    ['id' => 'e-flow-image', 'source' => 'whatsapp_flow-1', 'target' => 'image-1', 'sourceHandle' => 'condition_2'],
                    ['id' => 'e-image-pdf', 'source' => 'image-1', 'target' => 'pdf-1'],
                    ['id' => 'e-pdf-template', 'source' => 'pdf-1', 'target' => 'template-1'],
                    ['id' => 'e-q-store', 'source' => 'question-1', 'target' => 'datastore-1'],
                    ['id' => 'e-store-http', 'source' => 'datastore-1', 'target' => 'http-1'],
                    ['id' => 'e-http-mpesa', 'source' => 'http-1', 'target' => 'mpesa_stk_push-1'],
                    ['id' => 'e-mpesa-success', 'source' => 'mpesa_stk_push-1', 'target' => 'assign_group-1', 'sourceHandle' => 'mpesa-success'],
                    ['id' => 'e-mpesa-failed-end', 'source' => 'mpesa_stk_push-1', 'target' => 'end-1', 'sourceHandle' => 'mpesa-failed'],
                    ['id' => 'e-group-journey', 'source' => 'assign_group-1', 'target' => 'assign_journey_stage-1'],
                    ['id' => 'e-journey-template', 'source' => 'assign_journey_stage-1', 'target' => 'template-1'],
                    ['id' => 'e-template-end', 'source' => 'template-1', 'target' => 'end-1'],
                    ['id' => 'e-safari-video', 'source' => 'quick_replies-1', 'target' => 'video-1', 'sourceHandle' => 'button-1'],
                    ['id' => 'e-video-listing', 'source' => 'video-1', 'target' => 'listing_inquiry-2'],
                    ['id' => 'e-listing-end', 'source' => 'listing_inquiry-2', 'target' => 'end-1', 'sourceHandle' => 'onListingInquiry'],
                    ['id' => 'e-city-msg', 'source' => 'quick_replies-1', 'target' => 'message-2', 'sourceHandle' => 'button-2'],
                    ['id' => 'e-city-end', 'source' => 'message-2', 'target' => 'end-1'],
                    ['id' => 'e-custom-faq', 'source' => 'quick_replies-1', 'target' => 'tour-faq-question-initial', 'sourceHandle' => 'button-3'],
                ],
            ], [
                'idPrefix' => 'honeymoon-faq',
                'basePosition' => ['x' => 760, 'y' => 280],
                'questionInitial' => 'Tell me about your honeymoon plans — dates, room preferences, dietary needs, and any surprise arrangements.',
                'questionFollowup' => 'Anything else for your romantic getaway? Reply *done* when ready to continue booking, or *concierge* for a human advisor.',
                'systemPrompt' => 'You are a luxury travel concierge. Help with anniversary dates, room views, dietary restrictions, and surprise arrangements. Be warm and romantic. Never repeat the guest question — always provide a helpful response.',
                'llmVariableName' => 'honeymoon_details',
                'llmLabel' => 'Honeymoon concierge',
                'counterMax' => 5,
                'enableVectorSearch' => false,
                'humanKeywords' => ['concierge', 'agent', 'human'],
                'doneTarget' => 'question-1',
                'humanTarget' => 'assign_group-1',
            ]),
            [
                'idPrefix' => 'tour-faq',
                'basePosition' => ['x' => 760, 'y' => 680],
                'questionInitial' => 'Tell me about your dream trip — destinations, travel dates, group size, and budget.',
                'questionFollowup' => 'Want to refine the itinerary? Ask another question, reply *done* when happy, or *agent* to book with our team.',
                'systemPrompt' => 'You are a tour planning expert. Help gather destination preferences, travel dates, group size, and budget. Suggest day-by-day ideas. Never repeat the customer question — always provide a helpful response.',
                'llmVariableName' => 'custom_itinerary',
                'llmLabel' => 'Itinerary planner',
                'counterMax' => 4,
                'enableVectorSearch' => false,
                'doneTarget' => 'assign_group-1',
                'humanTarget' => 'assign_group-1',
            ]),
    ],

];
