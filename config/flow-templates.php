<?php

return [

    'lead_capture' => [
        'name' => 'Lead Capture',
        'description' => 'Collect name and interest when a customer messages a keyword.',
        'category' => 'sales',
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'setup_hint' => 'Customers type "interested" to start. Customize the welcome message in the flow editor.',
        'flow_data' => [
            'nodes' => [
                [
                    'id' => 'keyword_trigger-1',
                    'type' => 'keyword_trigger',
                    'position' => ['x' => 0, 'y' => 100],
                    'data' => [
                        'label' => 'On Keyword',
                        'type' => 'keyword_trigger',
                        'keywords' => [
                            ['id' => 'kw1', 'value' => 'interested', 'matchType' => 'contains'],
                        ],
                    ],
                ],
                [
                    'id' => 'message-1',
                    'type' => 'message',
                    'position' => ['x' => 400, 'y' => 100],
                    'data' => [
                        'label' => 'Message',
                        'type' => 'message',
                        'settings' => [
                            'message' => "Thanks for your interest! 🎉\n\nPlease share your name and what you're looking for, and our team will follow up shortly.",
                        ],
                    ],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 800, 'y' => 100],
                    'data' => ['label' => 'End', 'type' => 'end'],
                ],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'kw1'],
                ['id' => 'e2', 'source' => 'message-1', 'target' => 'end-1'],
            ],
        ],
    ],

    'payment_collection' => [
        'name' => 'Payment Collection (M-Pesa)',
        'description' => 'Send M-Pesa STK push when customer types "pay".',
        'category' => 'commerce',
        'video_url' => null,
        'setup_hint' => 'Configure M-Pesa Daraja credentials in Workspace → Apps before using this template.',
        'flow_data' => [
            'nodes' => [
                [
                    'id' => 'keyword_trigger-1',
                    'type' => 'keyword_trigger',
                    'position' => ['x' => 0, 'y' => 100],
                    'data' => [
                        'label' => 'On Keyword',
                        'type' => 'keyword_trigger',
                        'keywords' => [
                            ['id' => 'kw1', 'value' => 'pay', 'matchType' => 'contains'],
                        ],
                    ],
                ],
                [
                    'id' => 'message-1',
                    'type' => 'message',
                    'position' => ['x' => 350, 'y' => 100],
                    'data' => [
                        'label' => 'Message',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'We are sending an M-Pesa payment request to your phone. Please enter your PIN to complete payment.',
                        ],
                    ],
                ],
                [
                    'id' => 'mpesa_stk_push-1',
                    'type' => 'mpesa_stk_push',
                    'position' => ['x' => 700, 'y' => 100],
                    'data' => [
                        'label' => 'MPesa STK Push',
                        'type' => 'mpesa_stk_push',
                        'settings' => [
                            'mpesa' => [
                                'amount' => '100',
                                'accountReference' => 'ORDER',
                                'transactionDesc' => 'Payment',
                                'responseVar' => 'mpesa_result',
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 1050, 'y' => 100],
                    'data' => ['label' => 'End', 'type' => 'end'],
                ],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'kw1'],
                ['id' => 'e2', 'source' => 'message-1', 'target' => 'mpesa_stk_push-1'],
                ['id' => 'e3', 'source' => 'mpesa_stk_push-1', 'target' => 'end-1'],
            ],
        ],
    ],

    'appointment_booking' => [
        'name' => 'Appointment Booking',
        'description' => 'Guide customers to book via your public booking page.',
        'category' => 'bookings',
        'video_url' => null,
        'setup_hint' => 'Replace {booking_url} with your public booking link from Reminders → Booking pages.',
        'flow_data' => [
            'nodes' => [
                [
                    'id' => 'keyword_trigger-1',
                    'type' => 'keyword_trigger',
                    'position' => ['x' => 0, 'y' => 100],
                    'data' => [
                        'label' => 'On Keyword',
                        'type' => 'keyword_trigger',
                        'keywords' => [
                            ['id' => 'kw1', 'value' => 'book', 'matchType' => 'contains'],
                        ],
                    ],
                ],
                [
                    'id' => 'message-1',
                    'type' => 'message',
                    'position' => ['x' => 400, 'y' => 100],
                    'data' => [
                        'label' => 'Message',
                        'type' => 'message',
                        'settings' => [
                            'message' => "You can book an appointment here:\n{booking_url}\n\nReply with your preferred date and time if you need help.",
                        ],
                    ],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 800, 'y' => 100],
                    'data' => ['label' => 'End', 'type' => 'end'],
                ],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'kw1'],
                ['id' => 'e2', 'source' => 'message-1', 'target' => 'end-1'],
            ],
        ],
    ],

    'order_status' => [
        'name' => 'Order Status',
        'description' => 'Reply with order tracking info when customer asks about their order.',
        'category' => 'commerce',
        'video_url' => null,
        'setup_hint' => 'Customize the message with your order lookup process.',
        'flow_data' => [
            'nodes' => [
                [
                    'id' => 'keyword_trigger-1',
                    'type' => 'keyword_trigger',
                    'position' => ['x' => 0, 'y' => 100],
                    'data' => [
                        'label' => 'On Keyword',
                        'type' => 'keyword_trigger',
                        'keywords' => [
                            ['id' => 'kw1', 'value' => 'order', 'matchType' => 'contains'],
                            ['id' => 'kw2', 'value' => 'status', 'matchType' => 'contains'],
                        ],
                    ],
                ],
                [
                    'id' => 'message-1',
                    'type' => 'message',
                    'position' => ['x' => 400, 'y' => 100],
                    'data' => [
                        'label' => 'Message',
                        'type' => 'message',
                        'settings' => [
                            'message' => "Please share your order number and we'll check the status for you right away.",
                        ],
                    ],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 800, 'y' => 100],
                    'data' => ['label' => 'End', 'type' => 'end'],
                ],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'kw1'],
                ['id' => 'e2', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'kw2'],
                ['id' => 'e3', 'source' => 'message-1', 'target' => 'end-1'],
            ],
        ],
    ],

    'support_deflection' => [
        'name' => 'Support Deflection',
        'description' => 'Answer FAQs and offer knowledge base before escalating to an agent.',
        'category' => 'support',
        'video_url' => null,
        'setup_hint' => 'Connect a Knowledge base and add articles for best results. Add an OpenAI node for AI replies.',
        'flow_data' => [
            'nodes' => [
                [
                    'id' => 'keyword_trigger-1',
                    'type' => 'keyword_trigger',
                    'position' => ['x' => 0, 'y' => 100],
                    'data' => [
                        'label' => 'On Keyword',
                        'type' => 'keyword_trigger',
                        'keywords' => [
                            ['id' => 'kw1', 'value' => 'help', 'matchType' => 'contains'],
                        ],
                    ],
                ],
                [
                    'id' => 'message-1',
                    'type' => 'message',
                    'position' => ['x' => 400, 'y' => 100],
                    'data' => [
                        'label' => 'Message',
                        'type' => 'message',
                        'settings' => [
                            'message' => "I'm here to help! Browse our FAQ or describe your issue.\n\nType *agent* anytime to speak with our team.",
                        ],
                    ],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 800, 'y' => 100],
                    'data' => ['label' => 'End', 'type' => 'end'],
                ],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'kw1'],
                ['id' => 'e2', 'source' => 'message-1', 'target' => 'end-1'],
            ],
        ],
    ],

];
