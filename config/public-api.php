<?php

return [

    'version' => 'v1',

    'pagination' => [
        'default_limit' => 50,
        'max_limit' => 100,
        'contacts_hard_cap' => 100,
    ],

    'idempotency_ttl_hours' => 24,

    'rate_limits' => [
        'agency' => [
            'read' => 600,
            'write' => 120,
        ],
        'pro' => [
            'read' => 120,
            'write' => 30,
        ],
        'default' => [
            'read' => 60,
            'write' => 20,
        ],
    ],

    'webhook' => [
        'timeout' => 10,
        'signature_header' => 'X-ConvoConnect-Signature',
        'timestamp_header' => 'X-ConvoConnect-Timestamp',
        'max_attempts' => 8,
        'events' => [
            'message.received',
            'message.sent',
            'message.delivered',
            'message.read',
            'message.failed',
            'conversation.opened',
            'conversation.assigned',
            'conversation.resolved',
            'contact.created',
            'contact.updated',
            'booking.created',
            'booking.cancelled',
            'booking.rescheduled',
            'payment.completed',
            'payment.failed',
            'campaign.completed',
            'form.submitted',
            'order.created',
            'cart.abandoned',
            'fulfillment.shipped',
        ],
    ],

];
