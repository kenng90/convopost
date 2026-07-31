<?php

use App\Services\Flowmaker\FaqConversationLoop;

/**
 * Industry vertical flow templates (healthcare, real estate, automotive, banking, hospitality).
 *
 * @return array<string, array<string, mixed>>
 */
return array_merge([

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
                            ['id' => 'kw3', 'value' => 'help', 'matchType' => 'exact'],
                            ['id' => 'kw4', 'value' => 'agent', 'matchType' => 'exact'],
                            ['id' => 'kw5', 'value' => 'doctor', 'matchType' => 'exact'],
                            ['id' => 'kw6', 'value' => 'results', 'matchType' => 'contains'],
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
                            'footer' => 'Reply *help* anytime.',
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
                            'footer' => 'Reply *help* for the full menu.',
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
                            'conditions' => [],
                        ],
                    ],
                ],
                [
                    'id' => 'book_appointment-1',
                    'type' => 'book_appointment',
                    'position' => ['x' => 1140, 'y' => 280],
                    'data' => [
                        'label' => 'Confirm appointment slot',
                        'type' => 'book_appointment',
                        'settings' => [
                            'intake_mode' => 'form',
                            'duration_minutes' => '30',
                            'success_message' => 'Your appointment for {{booking_service}} on {{booking_date}} at {{booking_time}} is confirmed.',
                            'formFieldMap' => [
                                'serviceField' => 'select_3',
                                'dateField' => 'date_4',
                                'slotField' => 'slot',
                            ],
                            'serviceOptionMap' => [
                                'general' => 'General Practice',
                                'dental' => 'Dental',
                                'lab' => 'Lab Tests',
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'message-1',
                    'type' => 'message',
                    'position' => ['x' => 1520, 'y' => 240],
                    'data' => [
                        'label' => 'Booking confirmation',
                        'type' => 'message',
                        'settings' => [
                            'message' => "Your appointment is confirmed! ✅\n\nDepartment: {{form_department}}\nPreferred date: {{form_preferred_date}}\nService: {{booking_service}}\nWhen: {{booking_date}} at {{booking_time}}\n\nPlease review the pre-visit instructions attached. Reply *help* anytime.",
                        ],
                    ],
                ],
                [
                    'id' => 'message-book-unavailable',
                    'type' => 'message',
                    'position' => ['x' => 1520, 'y' => 400],
                    'data' => [
                        'label' => 'No slots available',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'Sorry, no open slot matched {{form_department}} near {{form_preferred_date}}. You can try another day or ask our team for help.',
                        ],
                    ],
                ],
                [
                    'id' => 'message-book-error',
                    'type' => 'message',
                    'position' => ['x' => 1520, 'y' => 520],
                    'data' => [
                        'label' => 'Booking could not complete',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'We could not complete that booking. No appointment should be assumed. Please retry or speak with our team.',
                        ],
                    ],
                ],
                [
                    'id' => 'quick_replies-book-recovery',
                    'type' => 'quick_replies',
                    'position' => ['x' => 1900, 'y' => 440],
                    'data' => [
                        'label' => 'Booking recovery',
                        'type' => 'quick_replies',
                        'settings' => [
                            'header' => 'What next?',
                            'body' => 'Choose another way to continue.',
                            'footer' => null,
                            'activeButtons' => 3,
                            'button1' => 'Try again',
                            'button2' => 'Main menu',
                            'button3' => 'Talk to team',
                        ],
                    ],
                ],
                [
                    'id' => 'message-lab',
                    'type' => 'message',
                    'position' => ['x' => 760, 'y' => 40],
                    'data' => [
                        'label' => 'Lab tests info',
                        'type' => 'message',
                        'settings' => [
                            'message' => "Lab tests can be booked as a visit or checked if already completed.\n\nReply *appointment* to book a lab visit, or *results* to request existing results.",
                        ],
                    ],
                ],
                [
                    'id' => 'quick_replies-lab',
                    'type' => 'quick_replies',
                    'position' => ['x' => 1140, 'y' => 40],
                    'data' => [
                        'label' => 'Lab next step',
                        'type' => 'quick_replies',
                        'settings' => [
                            'header' => 'Lab tests',
                            'body' => 'How would you like to continue?',
                            'footer' => null,
                            'activeButtons' => 3,
                            'button1' => 'Book lab visit',
                            'button2' => 'Get results',
                            'button3' => 'Talk to team',
                        ],
                    ],
                ],
                [
                    'id' => 'message-pharmacy',
                    'type' => 'message',
                    'position' => ['x' => 760, 'y' => 160],
                    'data' => [
                        'label' => 'Pharmacy info',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'Our pharmacy handles prescriptions and refills during clinic hours. A team member can help with refill status or collection.',
                        ],
                    ],
                ],
                [
                    'id' => 'message-directions',
                    'type' => 'message',
                    'position' => ['x' => 760, 'y' => -40],
                    'data' => [
                        'label' => 'Clinic directions',
                        'type' => 'message',
                        'settings' => [
                            'message' => "Find us at your clinic address (update this message with the real location, parking, and hours).\n\nChoose an option below.",
                        ],
                    ],
                ],
                [
                    'id' => 'quick_replies-directions',
                    'type' => 'quick_replies',
                    'position' => ['x' => 1140, 'y' => -40],
                    'data' => [
                        'label' => 'Directions next',
                        'type' => 'quick_replies',
                        'settings' => [
                            'header' => 'What next?',
                            'body' => 'Book a visit, talk to our team, or return to the menu.',
                            'footer' => 'Reply *help* anytime.',
                            'activeButtons' => 3,
                            'button1' => 'Book appointment',
                            'button2' => 'Talk to team',
                            'button3' => 'Main menu',
                        ],
                    ],
                ],
                [
                    'id' => 'assign_agent-handoff',
                    'type' => 'assign_agent',
                    'position' => ['x' => 1900, 'y' => 880],
                    'data' => [
                        'label' => 'Assign clinic agent',
                        'type' => 'assign_agent',
                        'settings' => ['agentId' => 'none'],
                    ],
                ],
                [
                    'id' => 'assign_group-handoff',
                    'type' => 'assign_group',
                    'position' => ['x' => 2280, 'y' => 880],
                    'data' => [
                        'label' => 'Clinic support team',
                        'type' => 'assign_group',
                        'settings' => ['groupId' => '1', 'action' => 'add'],
                    ],
                ],
                [
                    'id' => 'message-handoff',
                    'type' => 'message',
                    'position' => ['x' => 2660, 'y' => 880],
                    'data' => [
                        'label' => 'Team handoff',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'A member of our clinic team will reply here shortly. Please share any patient ID or booking details that may help.',
                        ],
                    ],
                ],
                [
                    'id' => 'pdf-1',
                    'type' => 'pdf',
                    'position' => ['x' => 1900, 'y' => 240],
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
                        'label' => 'Assign clinic agent',
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
                            'message' => 'Thank you. Your lab results request for patient ID {{patient_id}} has been queued. A member of the Results Team will review and reply here shortly.',
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
                            'message' => 'Thanks for the details. Reply *appointment* to book, *results* for lab lookup, or *agent* to speak with our team. For emergencies, call your local emergency number immediately.',
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
                ['id' => 'e-help-list', 'source' => 'keyword_trigger-1', 'target' => 'list_message-1', 'sourceHandle' => 'keyword-kw3'],
                ['id' => 'e-agent-kw', 'source' => 'keyword_trigger-1', 'target' => 'assign_agent-handoff', 'sourceHandle' => 'keyword-kw4'],
                ['id' => 'e-doctor-kw', 'source' => 'keyword_trigger-1', 'target' => 'triage-faq-question-initial', 'sourceHandle' => 'keyword-kw5'],
                ['id' => 'e-results-kw', 'source' => 'keyword_trigger-1', 'target' => 'question-1', 'sourceHandle' => 'keyword-kw6'],
                ['id' => 'e-list-book', 'source' => 'list_message-1', 'target' => 'whatsapp_flow-1', 'sourceHandle' => 'section1-row1'],
                ['id' => 'e-list-lab', 'source' => 'list_message-1', 'target' => 'message-lab', 'sourceHandle' => 'section1-row2'],
                ['id' => 'e-list-pharmacy', 'source' => 'list_message-1', 'target' => 'message-pharmacy', 'sourceHandle' => 'section1-row3'],
                ['id' => 'e-list-doctor', 'source' => 'list_message-1', 'target' => 'triage-faq-question-initial', 'sourceHandle' => 'section2-row1'],
                ['id' => 'e-list-billing', 'source' => 'list_message-1', 'target' => 'assign_agent-handoff', 'sourceHandle' => 'section2-row2'],
                ['id' => 'e-list-directions', 'source' => 'list_message-1', 'target' => 'message-directions', 'sourceHandle' => 'section2-row3'],
                ['id' => 'e-lab-qr', 'source' => 'message-lab', 'target' => 'quick_replies-lab'],
                ['id' => 'e-lab-book', 'source' => 'quick_replies-lab', 'target' => 'whatsapp_flow-1', 'sourceHandle' => 'button-1'],
                ['id' => 'e-lab-results', 'source' => 'quick_replies-lab', 'target' => 'question-1', 'sourceHandle' => 'button-2'],
                ['id' => 'e-lab-agent', 'source' => 'quick_replies-lab', 'target' => 'assign_agent-handoff', 'sourceHandle' => 'button-3'],
                ['id' => 'e-pharmacy-agent', 'source' => 'message-pharmacy', 'target' => 'assign_agent-handoff'],
                ['id' => 'e-directions-next', 'source' => 'message-directions', 'target' => 'quick_replies-directions'],
                ['id' => 'e-dir-book', 'source' => 'quick_replies-directions', 'target' => 'quick_replies-1', 'sourceHandle' => 'button-1'],
                ['id' => 'e-dir-agent', 'source' => 'quick_replies-directions', 'target' => 'assign_agent-handoff', 'sourceHandle' => 'button-2'],
                ['id' => 'e-dir-menu', 'source' => 'quick_replies-directions', 'target' => 'list_message-1', 'sourceHandle' => 'button-3'],
                ['id' => 'e-book-flow', 'source' => 'quick_replies-1', 'target' => 'whatsapp_flow-1', 'sourceHandle' => 'button-1'],
                ['id' => 'e-talk-faq', 'source' => 'quick_replies-1', 'target' => 'triage-faq-question-initial', 'sourceHandle' => 'button-2'],
                ['id' => 'e-results-q', 'source' => 'quick_replies-1', 'target' => 'question-1', 'sourceHandle' => 'button-3'],
                ['id' => 'e-flow-book', 'source' => 'whatsapp_flow-1', 'target' => 'book_appointment-1', 'sourceHandle' => 'onFlowCompleted'],
                ['id' => 'e-book-confirm', 'source' => 'book_appointment-1', 'target' => 'message-1', 'sourceHandle' => 'success'],
                ['id' => 'e-book-unavailable', 'source' => 'book_appointment-1', 'target' => 'message-book-unavailable', 'sourceHandle' => 'unavailable'],
                ['id' => 'e-book-error', 'source' => 'book_appointment-1', 'target' => 'message-book-error', 'sourceHandle' => 'error'],
                ['id' => 'e-confirm-pdf', 'source' => 'message-1', 'target' => 'pdf-1'],
                ['id' => 'e-pdf-end', 'source' => 'pdf-1', 'target' => 'end-1'],
                ['id' => 'e-unavailable-recovery', 'source' => 'message-book-unavailable', 'target' => 'quick_replies-book-recovery'],
                ['id' => 'e-error-recovery', 'source' => 'message-book-error', 'target' => 'quick_replies-book-recovery'],
                ['id' => 'e-recovery-retry', 'source' => 'quick_replies-book-recovery', 'target' => 'whatsapp_flow-1', 'sourceHandle' => 'button-1'],
                ['id' => 'e-recovery-menu', 'source' => 'quick_replies-book-recovery', 'target' => 'list_message-1', 'sourceHandle' => 'button-2'],
                ['id' => 'e-recovery-agent', 'source' => 'quick_replies-book-recovery', 'target' => 'assign_agent-handoff', 'sourceHandle' => 'button-3'],
                ['id' => 'e-q-store', 'source' => 'question-1', 'target' => 'datastore-1'],
                ['id' => 'e-store-http', 'source' => 'datastore-1', 'target' => 'http-1'],
                ['id' => 'e-http-agent', 'source' => 'http-1', 'target' => 'assign_agent-1'],
                ['id' => 'e-agent-group', 'source' => 'assign_agent-1', 'target' => 'assign_group-1'],
                ['id' => 'e-group-journey', 'source' => 'assign_group-1', 'target' => 'assign_journey_stage-1'],
                ['id' => 'e-journey-msg', 'source' => 'assign_journey_stage-1', 'target' => 'message-2'],
                ['id' => 'e-results-end', 'source' => 'message-2', 'target' => 'end-1'],
                ['id' => 'e-handoff-group', 'source' => 'assign_agent-handoff', 'target' => 'assign_group-handoff'],
                ['id' => 'e-handoff-msg', 'source' => 'assign_group-handoff', 'target' => 'message-handoff'],
                ['id' => 'e-handoff-end', 'source' => 'message-handoff', 'target' => 'end-1'],
                ['id' => 'e-triage-done-end', 'source' => 'message-3', 'target' => 'end-1'],
            ],
        ], [
            'idPrefix' => 'triage-faq',
            'basePosition' => ['x' => 760, 'y' => 520],
            'questionInitial' => 'Please describe your symptoms or health concern. For emergencies, call your local emergency number immediately.',
            'questionFollowup' => 'Any other symptoms or questions? Reply *done* when finished, *urgent* if urgent, *appointment* to book, *help* for the menu, or *doctor* / *agent* for a clinician.',
            'systemPrompt' => 'You are a medical triage assistant. Assess urgency, ask one clarifying question at a time, and recommend next steps. Never diagnose. Never repeat the patient question — always provide a helpful response. End with: For emergencies, call your local emergency number immediately.',
            'llmVariableName' => 'triage_reply',
            'llmLabel' => 'Medical triage AI',
            'counterMax' => 3,
            'freeExecutions' => 5,
            'enableVectorSearch' => false,
            'humanKeywords' => ['doctor', 'agent', 'human'],
            'keywordExits' => [
                ['id' => 'cond-urgent', 'keyword' => 'urgent', 'target' => 'message-3'],
                ['id' => 'cond-appointment', 'keyword' => 'appointment', 'target' => 'quick_replies-1'],
                ['id' => 'cond-help', 'keyword' => 'help', 'target' => 'list_message-1'],
                ['id' => 'cond-results', 'keyword' => 'results', 'target' => 'question-1'],
            ],
            'doneTarget' => 'message-3',
            'humanTarget' => 'assign_agent-handoff',
            'limitTarget' => 'assign_agent-handoff',
        ]),
    ],

], require __DIR__.'/flow-templates-property-automotive.php', [
    'microfinance_banking_bot' => [
        'name' => 'Microfinance Banking Bot',
        'description' => 'Balance checks, loan applications, repayments via payment request, and AI-formatted loan status updates.',
        'category' => 'banking',
        'form_bundle' => 'microfinance_loan_application',
        'video_url' => null,
        'setup_hint' => 'Configure banking API, payment gateway, loan template, Repayments group. LLM auto-sends loan status (no duplicate message). Save draft → Publish.',
        'post_install_checklist' => ['Banking API URLs', 'Payment gateway credentials', 'Repayments group', 'Publish'],
        'exclusive_on_match' => true,
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
                            ['id' => 'kw3', 'value' => 'help', 'matchType' => 'exact'],
                            ['id' => 'kw4', 'value' => 'agent', 'matchType' => 'exact'],
                            ['id' => 'kw5', 'value' => 'repay', 'matchType' => 'contains'],
                            ['id' => 'kw6', 'value' => 'menu', 'matchType' => 'exact'],
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
                    'id' => 'message-verify-failed',
                    'type' => 'message',
                    'position' => ['x' => 760, 'y' => 200],
                    'data' => [
                        'label' => 'Verification needed',
                        'type' => 'message',
                        'settings' => [
                            'message' => "We could not verify your account for a balance check yet.\n\nReply *loan* for loan services, or *agent* for help from our team.",
                        ],
                    ],
                ],
                [
                    'id' => 'message-limit',
                    'type' => 'message',
                    'position' => ['x' => 1140, 'y' => 200],
                    'data' => [
                        'label' => 'Limit reached',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'You have reached the balance-check limit for this period. Reply *loan* for other services or *agent* for assistance.',
                        ],
                    ],
                ],
                [
                    'id' => 'quick_replies-balance-next',
                    'type' => 'quick_replies',
                    'position' => ['x' => 2660, 'y' => 40],
                    'data' => [
                        'label' => 'After balance',
                        'type' => 'quick_replies',
                        'settings' => [
                            'header' => 'Anything else?',
                            'body' => 'Continue with loan services or speak with our team.',
                            'footer' => 'Reply *help* anytime.',
                            'activeButtons' => 3,
                            'button1' => 'Loan services',
                            'button2' => 'Talk to agent',
                            'button3' => 'Done',
                        ],
                    ],
                ],
                [
                    'id' => 'quick_replies-recovery',
                    'type' => 'quick_replies',
                    'position' => ['x' => 1140, 'y' => 280],
                    'data' => [
                        'label' => 'Recovery options',
                        'type' => 'quick_replies',
                        'settings' => [
                            'header' => 'What next?',
                            'body' => 'Choose how you would like to continue.',
                            'footer' => 'Reply *help* for loan menu.',
                            'activeButtons' => 3,
                            'button1' => 'Loan services',
                            'button2' => 'Talk to agent',
                            'button3' => 'Done',
                        ],
                    ],
                ],
                [
                    'id' => 'quick_replies-pay-recovery',
                    'type' => 'quick_replies',
                    'position' => ['x' => 2280, 'y' => 600],
                    'data' => [
                        'label' => 'Payment recovery',
                        'type' => 'quick_replies',
                        'settings' => [
                            'header' => 'Payment not completed',
                            'body' => 'Retry repayment, edit the loan account, or talk to our team.',
                            'footer' => 'Reply *loan* for the menu.',
                            'activeButtons' => 3,
                            'button1' => 'Retry payment',
                            'button2' => 'Edit account',
                            'button3' => 'Talk to agent',
                        ],
                    ],
                ],
                [
                    'id' => 'message-pay-failed',
                    'type' => 'message',
                    'position' => ['x' => 2090, 'y' => 600],
                    'data' => [
                        'label' => 'Payment failed',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'We could not complete that repayment. No charge should be assumed. Choose an option below.',
                        ],
                    ],
                ],
                [
                    'id' => 'assign_agent-1',
                    'type' => 'assign_agent',
                    'position' => ['x' => 3040, 'y' => 200],
                    'data' => [
                        'label' => 'Banking agent',
                        'type' => 'assign_agent',
                        'settings' => ['agentId' => 'none'],
                    ],
                ],
                [
                    'id' => 'message-handoff',
                    'type' => 'message',
                    'position' => ['x' => 3420, 'y' => 200],
                    'data' => [
                        'label' => 'Agent handoff',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'A banking specialist will reply here shortly. Thank you for your patience.',
                        ],
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
                            'footer' => 'Reply *agent* for a human.',
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
                    'id' => 'request_payment-1',
                    'type' => 'request_payment',
                    'position' => ['x' => 1900, 'y' => 520],
                    'data' => [
                        'label' => 'Collect repayment',
                        'type' => 'request_payment',
                        'settings' => [
                            'payment' => [
                                'amount' => '{{repayment_amount}}',
                                'accountReference' => 'LOAN-REPAY',
                                'description' => 'Loan repayment',
                                'provider' => 'auto',
                                'email' => '',
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
                            'message' => "Payment received.\n\nAccount: {{loan_account}}\nReference: {{repayment_result}}\n\nThank you for your repayment.",
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
                ['id' => 'e-help-qr', 'source' => 'keyword_trigger-1', 'target' => 'quick_replies-1', 'sourceHandle' => 'keyword-kw3'],
                ['id' => 'e-agent-kw', 'source' => 'keyword_trigger-1', 'target' => 'assign_agent-1', 'sourceHandle' => 'keyword-kw4'],
                ['id' => 'e-repay-kw', 'source' => 'keyword_trigger-1', 'target' => 'question-1', 'sourceHandle' => 'keyword-kw5'],
                ['id' => 'e-menu-kw', 'source' => 'keyword_trigger-1', 'target' => 'quick_replies-1', 'sourceHandle' => 'keyword-kw6'],
                ['id' => 'e-incoming-branch', 'source' => 'incomingMessage-1', 'target' => 'branch-1'],
                ['id' => 'e-verified-counter', 'source' => 'branch-1', 'target' => 'counter-1', 'sourceHandle' => 'condition-cond-verified-true'],
                ['id' => 'e-branch-false', 'source' => 'branch-1', 'target' => 'message-verify-failed', 'sourceHandle' => 'condition-cond-verified-false'],
                ['id' => 'e-verify-recovery', 'source' => 'message-verify-failed', 'target' => 'quick_replies-recovery'],
                ['id' => 'e-counter-pricing', 'source' => 'counter-1', 'target' => 'check_pricing-1', 'sourceHandle' => 'true'],
                ['id' => 'e-counter-false', 'source' => 'counter-1', 'target' => 'message-limit', 'sourceHandle' => 'false'],
                ['id' => 'e-limit-recovery', 'source' => 'message-limit', 'target' => 'quick_replies-recovery'],
                ['id' => 'e-pricing-http', 'source' => 'check_pricing-1', 'target' => 'http-1', 'sourceHandle' => 'true'],
                ['id' => 'e-pricing-false', 'source' => 'check_pricing-1', 'target' => 'message-limit', 'sourceHandle' => 'false'],
                ['id' => 'e-http-balance', 'source' => 'http-1', 'target' => 'message-1'],
                ['id' => 'e-balance-next', 'source' => 'message-1', 'target' => 'quick_replies-balance-next'],
                ['id' => 'e-bal-loan', 'source' => 'quick_replies-balance-next', 'target' => 'quick_replies-1', 'sourceHandle' => 'button-1'],
                ['id' => 'e-bal-agent', 'source' => 'quick_replies-balance-next', 'target' => 'assign_agent-1', 'sourceHandle' => 'button-2'],
                ['id' => 'e-bal-done', 'source' => 'quick_replies-balance-next', 'target' => 'end-1', 'sourceHandle' => 'button-3'],
                ['id' => 'e-rec-loan', 'source' => 'quick_replies-recovery', 'target' => 'quick_replies-1', 'sourceHandle' => 'button-1'],
                ['id' => 'e-rec-agent', 'source' => 'quick_replies-recovery', 'target' => 'assign_agent-1', 'sourceHandle' => 'button-2'],
                ['id' => 'e-rec-done', 'source' => 'quick_replies-recovery', 'target' => 'end-1', 'sourceHandle' => 'button-3'],
                ['id' => 'e-apply-flow', 'source' => 'quick_replies-1', 'target' => 'whatsapp_flow-1', 'sourceHandle' => 'button-1'],
                ['id' => 'e-status-q', 'source' => 'quick_replies-1', 'target' => 'question-2', 'sourceHandle' => 'button-2'],
                ['id' => 'e-repay-q', 'source' => 'quick_replies-1', 'target' => 'question-1', 'sourceHandle' => 'button-3'],
                ['id' => 'e-flow-template', 'source' => 'whatsapp_flow-1', 'target' => 'template-1', 'sourceHandle' => 'condition_3'],
                ['id' => 'e-template-end', 'source' => 'template-1', 'target' => 'end-1'],
                ['id' => 'e-q-store', 'source' => 'question-1', 'target' => 'datastore-1'],
                ['id' => 'e-store-verify', 'source' => 'datastore-1', 'target' => 'http-2'],
                ['id' => 'e-verify-pay', 'source' => 'http-2', 'target' => 'request_payment-1'],
                ['id' => 'e-pay-success', 'source' => 'request_payment-1', 'target' => 'message-2', 'sourceHandle' => 'success'],
                ['id' => 'e-pay-failed', 'source' => 'request_payment-1', 'target' => 'message-pay-failed', 'sourceHandle' => 'failed'],
                ['id' => 'e-pay-fail-recovery', 'source' => 'message-pay-failed', 'target' => 'quick_replies-pay-recovery'],
                ['id' => 'e-retry-pay', 'source' => 'quick_replies-pay-recovery', 'target' => 'request_payment-1', 'sourceHandle' => 'button-1'],
                ['id' => 'e-edit-account', 'source' => 'quick_replies-pay-recovery', 'target' => 'question-1', 'sourceHandle' => 'button-2'],
                ['id' => 'e-pay-agent', 'source' => 'quick_replies-pay-recovery', 'target' => 'assign_agent-1', 'sourceHandle' => 'button-3'],
                ['id' => 'e-receipt-group', 'source' => 'message-2', 'target' => 'assign_group-1'],
                ['id' => 'e-group-journey', 'source' => 'assign_group-1', 'target' => 'assign_journey_stage-1'],
                ['id' => 'e-journey-end', 'source' => 'assign_journey_stage-1', 'target' => 'end-1'],
                ['id' => 'e-status-store', 'source' => 'question-2', 'target' => 'datastore-2'],
                ['id' => 'e-store-status-http', 'source' => 'datastore-2', 'target' => 'http-3'],
                ['id' => 'e-http-openai', 'source' => 'http-3', 'target' => 'openai-1'],
                ['id' => 'e-openai-status', 'source' => 'openai-1', 'target' => 'message-3'],
                ['id' => 'e-status-end', 'source' => 'message-3', 'target' => 'quick_replies-balance-next'],
                ['id' => 'e-agent-handoff', 'source' => 'assign_agent-1', 'target' => 'message-handoff'],
                ['id' => 'e-handoff-end', 'source' => 'message-handoff', 'target' => 'end-1'],
            ],
        ],
    ],

    'hotel_tour_concierge_bot' => [
        'name' => 'Hotel & Tour Concierge Bot',
        'description' => 'Room bookings, honeymoon packages, safari tours, custom itineraries, and booking confirmations.',
        'category' => 'hospitality',
        'form_bundle' => 'hospitality_booking',
        'video_url' => null,
        'setup_hint' => 'Link booking WhatsApp Flows, listing catalog, payment deposits, VIP group. FAQ paths use multi-turn AI loops. Save draft → Publish.',
        'post_install_checklist' => [
            'Suite WhatsApp Flow ID',
            'Safari listing catalog ID',
            'Payment deposit settings',
            'VIP Guests group',
            'Forms capture inquiry — bind to PMS/HTTP or a bookable Source later',
            'Publish',
        ],
        'exclusive_on_match' => true,
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
                                ['id' => 'kw3', 'value' => 'help', 'matchType' => 'exact'],
                                ['id' => 'kw4', 'value' => 'agent', 'matchType' => 'exact'],
                                ['id' => 'kw5', 'value' => 'concierge', 'matchType' => 'exact'],
                                ['id' => 'kw6', 'value' => 'menu', 'matchType' => 'exact'],
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
                                'footer' => 'Reply *tour* or *agent* anytime.',
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
                                'conditions' => [],
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
                        'id' => 'question-package',
                        'type' => 'question',
                        'position' => ['x' => 950, 'y' => 200],
                        'data' => [
                            'label' => 'Confirm package',
                            'type' => 'question',
                            'settings' => [
                                'question' => "You selected *{{contact_last_message}}*.\n\nReply *yes* to continue, or type a different room or package name.",
                                'variableName' => 'selected_package',
                            ],
                        ],
                    ],
                    [
                        'id' => 'message-pay-failed',
                        'type' => 'message',
                        'position' => ['x' => 2280, 'y' => 400],
                        'data' => [
                            'label' => 'Deposit failed',
                            'type' => 'message',
                            'settings' => [
                                'message' => 'We could not collect the deposit. No charge should be assumed. Choose an option below.',
                            ],
                        ],
                    ],
                    [
                        'id' => 'quick_replies-pay-recovery',
                        'type' => 'quick_replies',
                        'position' => ['x' => 2470, 'y' => 400],
                        'data' => [
                            'label' => 'Deposit recovery',
                            'type' => 'quick_replies',
                            'settings' => [
                                'header' => 'What next?',
                                'body' => 'Retry the deposit, edit guest details, or talk to concierge.',
                                'footer' => 'Reply *book* for the menu.',
                                'activeButtons' => 3,
                                'button1' => 'Retry deposit',
                                'button2' => 'Edit details',
                                'button3' => 'Talk to concierge',
                            ],
                        ],
                    ],
                    [
                        'id' => 'message-safari-received',
                        'type' => 'message',
                        'position' => ['x' => 1520, 'y' => 480],
                        'data' => [
                            'label' => 'Safari booking received',
                            'type' => 'message',
                            'settings' => [
                                'message' => 'Thanks {{safari_booking_customer_name}}! We received your safari request for {{safari_booking_item_title}} on {{safari_booking_preferred_datetime}}. Our team will confirm shortly.',
                            ],
                        ],
                    ],
                    [
                        'id' => 'quick_replies-city-next',
                        'type' => 'quick_replies',
                        'position' => ['x' => 2090, 'y' => 480],
                        'data' => [
                            'label' => 'City tours next',
                            'type' => 'quick_replies',
                            'settings' => [
                                'header' => 'Ready to continue?',
                                'body' => 'Book a stay, talk to concierge, or explore more tours.',
                                'footer' => 'Reply *tour* anytime.',
                                'activeButtons' => 3,
                                'button1' => 'Book a stay',
                                'button2' => 'Talk to concierge',
                                'button3' => 'More tours',
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
                                'variableValue' => '{{guest_count}} | Package: {{selected_package}} | Details: {{honeymoon_details}}',
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
                                        ['id' => 'p-package', 'key' => 'package', 'value' => '{{selected_package}}'],
                                        ['id' => 'p-notes', 'key' => 'notes', 'value' => '{{honeymoon_details}}'],
                                    ],
                                    'responseVar' => 'booking_result',
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => 'request_payment-1',
                        'type' => 'request_payment',
                        'position' => ['x' => 2280, 'y' => 280],
                        'data' => [
                            'label' => '30% deposit',
                            'type' => 'request_payment',
                            'settings' => [
                                'payment' => [
                                    'amount' => '{{deposit_amount}}',
                                    'accountReference' => 'HOTEL-DEPOSIT',
                                    'description' => 'Booking deposit (30%)',
                                    'provider' => 'auto',
                                    'email' => '',
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
                        'id' => 'assign_group-handoff',
                        'type' => 'assign_group',
                        'position' => ['x' => 2660, 'y' => 80],
                        'data' => [
                            'label' => 'Concierge queue',
                            'type' => 'assign_group',
                            'settings' => ['groupId' => '1', 'action' => 'add'],
                        ],
                    ],
                    [
                        'id' => 'message-handoff',
                        'type' => 'message',
                        'position' => ['x' => 3040, 'y' => 80],
                        'data' => [
                            'label' => 'Concierge handoff',
                            'type' => 'message',
                            'settings' => [
                                'message' => 'A concierge will reply here shortly. Thank you for your patience.',
                            ],
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
                                    'guest_name' => '{{form_guest_name}}',
                                    'booking_reference' => '{{booking_result.reference}}',
                                    'check_in_date' => '{{form_check_in_date}}',
                                    'check_out_date' => '{{form_check_out_date}}',
                                    'room_type' => '{{form_room_type}}',
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
                                'footer' => 'Reply *book* for rooms.',
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
                                'displayMode' => 'interactive_list',
                                'autoResumeFlow' => true,
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
                                'message' => 'Our city tours run daily at 9 AM and 2 PM. Choose an option below, or reply *book* to reserve a stay.',
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
                    ['id' => 'e-help-list', 'source' => 'keyword_trigger-1', 'target' => 'list_message-1', 'sourceHandle' => 'keyword-kw3'],
                    ['id' => 'e-agent-kw', 'source' => 'keyword_trigger-1', 'target' => 'assign_group-handoff', 'sourceHandle' => 'keyword-kw4'],
                    ['id' => 'e-concierge-kw', 'source' => 'keyword_trigger-1', 'target' => 'assign_group-handoff', 'sourceHandle' => 'keyword-kw5'],
                    ['id' => 'e-menu-kw', 'source' => 'keyword_trigger-1', 'target' => 'list_message-1', 'sourceHandle' => 'keyword-kw6'],
                    ['id' => 'e-std-pkg', 'source' => 'list_message-1', 'target' => 'question-package', 'sourceHandle' => 'section1-row1'],
                    ['id' => 'e-deluxe-pkg', 'source' => 'list_message-1', 'target' => 'question-package', 'sourceHandle' => 'section1-row2'],
                    ['id' => 'e-suite-flow', 'source' => 'list_message-1', 'target' => 'whatsapp_flow-1', 'sourceHandle' => 'section1-row3'],
                    ['id' => 'e-honeymoon-faq', 'source' => 'list_message-1', 'target' => 'honeymoon-faq-question-initial', 'sourceHandle' => 'section2-row1'],
                    ['id' => 'e-family-pkg', 'source' => 'list_message-1', 'target' => 'question-package', 'sourceHandle' => 'section2-row2'],
                    ['id' => 'e-business-pkg', 'source' => 'list_message-1', 'target' => 'question-package', 'sourceHandle' => 'section2-row3'],
                    ['id' => 'e-pkg-guests', 'source' => 'question-package', 'target' => 'question-1'],
                    ['id' => 'e-flow-image', 'source' => 'whatsapp_flow-1', 'target' => 'image-1', 'sourceHandle' => 'onFlowCompleted'],
                    ['id' => 'e-image-pdf', 'source' => 'image-1', 'target' => 'pdf-1'],
                    ['id' => 'e-pdf-template', 'source' => 'pdf-1', 'target' => 'template-1'],
                    ['id' => 'e-q-store', 'source' => 'question-1', 'target' => 'datastore-1'],
                    ['id' => 'e-store-http', 'source' => 'datastore-1', 'target' => 'http-1'],
                    ['id' => 'e-http-pay', 'source' => 'http-1', 'target' => 'request_payment-1'],
                    ['id' => 'e-pay-success', 'source' => 'request_payment-1', 'target' => 'assign_group-1', 'sourceHandle' => 'success'],
                    ['id' => 'e-pay-failed', 'source' => 'request_payment-1', 'target' => 'message-pay-failed', 'sourceHandle' => 'failed'],
                    ['id' => 'e-pay-fail-recovery', 'source' => 'message-pay-failed', 'target' => 'quick_replies-pay-recovery'],
                    ['id' => 'e-retry-deposit', 'source' => 'quick_replies-pay-recovery', 'target' => 'request_payment-1', 'sourceHandle' => 'button-1'],
                    ['id' => 'e-edit-guests', 'source' => 'quick_replies-pay-recovery', 'target' => 'question-1', 'sourceHandle' => 'button-2'],
                    ['id' => 'e-pay-concierge', 'source' => 'quick_replies-pay-recovery', 'target' => 'assign_group-handoff', 'sourceHandle' => 'button-3'],
                    ['id' => 'e-group-journey', 'source' => 'assign_group-1', 'target' => 'assign_journey_stage-1'],
                    ['id' => 'e-journey-template', 'source' => 'assign_journey_stage-1', 'target' => 'template-1'],
                    ['id' => 'e-template-end', 'source' => 'template-1', 'target' => 'end-1'],
                    ['id' => 'e-handoff-msg', 'source' => 'assign_group-handoff', 'target' => 'message-handoff'],
                    ['id' => 'e-handoff-end', 'source' => 'message-handoff', 'target' => 'end-1'],
                    ['id' => 'e-safari-video', 'source' => 'quick_replies-1', 'target' => 'video-1', 'sourceHandle' => 'button-1'],
                    ['id' => 'e-video-listing', 'source' => 'video-1', 'target' => 'listing_inquiry-2'],
                    ['id' => 'e-listing-confirm', 'source' => 'listing_inquiry-2', 'target' => 'message-safari-received', 'sourceHandle' => 'onListingInquiry'],
                    ['id' => 'e-safari-group', 'source' => 'message-safari-received', 'target' => 'assign_group-handoff'],
                    ['id' => 'e-city-msg', 'source' => 'quick_replies-1', 'target' => 'message-2', 'sourceHandle' => 'button-2'],
                    ['id' => 'e-city-next', 'source' => 'message-2', 'target' => 'quick_replies-city-next'],
                    ['id' => 'e-city-book', 'source' => 'quick_replies-city-next', 'target' => 'list_message-1', 'sourceHandle' => 'button-1'],
                    ['id' => 'e-city-agent', 'source' => 'quick_replies-city-next', 'target' => 'assign_group-handoff', 'sourceHandle' => 'button-2'],
                    ['id' => 'e-city-tours', 'source' => 'quick_replies-city-next', 'target' => 'quick_replies-1', 'sourceHandle' => 'button-3'],
                    ['id' => 'e-custom-faq', 'source' => 'quick_replies-1', 'target' => 'tour-faq-question-initial', 'sourceHandle' => 'button-3'],
                ],
            ], [
                'idPrefix' => 'honeymoon-faq',
                'basePosition' => ['x' => 760, 'y' => 280],
                'questionInitial' => 'Tell me about your honeymoon plans — dates, room preferences, dietary needs, and any surprise arrangements.',
                'questionFollowup' => 'Anything else? Reply *done* to continue booking, *book* for the menu, *tour* for experiences, or *concierge* / *agent* for a human.',
                'systemPrompt' => 'You are a luxury travel concierge. Help with anniversary dates, room views, dietary restrictions, and surprise arrangements. Be warm and romantic. Never repeat the guest question — always provide a helpful response.',
                'llmVariableName' => 'honeymoon_details',
                'llmLabel' => 'Honeymoon concierge',
                'counterMax' => 5,
                'enableVectorSearch' => false,
                'humanKeywords' => ['concierge', 'agent', 'human'],
                'keywordExits' => [
                    ['id' => 'cond-book', 'keyword' => 'book', 'target' => 'list_message-1'],
                    ['id' => 'cond-tour', 'keyword' => 'tour', 'target' => 'quick_replies-1'],
                    ['id' => 'cond-help', 'keyword' => 'help', 'target' => 'list_message-1'],
                ],
                'doneTarget' => 'question-1',
                'humanTarget' => 'assign_group-handoff',
            ]),
            [
                'idPrefix' => 'tour-faq',
                'basePosition' => ['x' => 760, 'y' => 680],
                'questionInitial' => 'Tell me about your dream trip — destinations, travel dates, group size, and budget.',
                'questionFollowup' => 'Want to refine the itinerary? Ask another question, reply *done* when happy, *book* for rooms, *tour* for the menu, or *agent* for our team.',
                'systemPrompt' => 'You are a tour planning expert. Help gather destination preferences, travel dates, group size, and budget. Suggest day-by-day ideas. Never repeat the customer question — always provide a helpful response.',
                'llmVariableName' => 'custom_itinerary',
                'llmLabel' => 'Itinerary planner',
                'counterMax' => 4,
                'enableVectorSearch' => false,
                'keywordExits' => [
                    ['id' => 'cond-book', 'keyword' => 'book', 'target' => 'list_message-1'],
                    ['id' => 'cond-tour', 'keyword' => 'tour', 'target' => 'quick_replies-1'],
                    ['id' => 'cond-help', 'keyword' => 'help', 'target' => 'list_message-1'],
                ],
                'doneTarget' => 'assign_group-handoff',
                'humanTarget' => 'assign_group-handoff',
            ]),
    ],

]);
