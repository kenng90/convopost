<?php

use Modules\Reminders\Services\BookingMessageContextService as Fields;

/**
 * Curated WhatsApp booking message templates + reminder campaign mappings.
 *
 * @return array<string, array<string, mixed>>
 */
return [
    'event_booking_confirmation' => [
        'template_name' => 'event_booking_confirmation_v2',
        'campaign_name' => 'Event booking confirmation',
        'category' => 'UTILITY',
        'language' => 'en',
        'body' => "Hello {{1}},\n\nYour registration for *{{2}}* has been confirmed.\n\n🔖 Reference: {{3}}\n📅 Date: {{4}}\n🕒 Time: {{5}}\n📍 Venue: {{6}}\n\nKeep this reference to cancel or manage your registration online.\n\nIf you have any questions, reply to this message.\n\nSee you soon!",
        'example' => [
            'Jane Customer',
            'Annual Summit',
            '#42',
            'Aug 1, 2026',
            '10:00 AM',
            'Main Hall',
        ],
        'variables_match' => [
            'body' => [
                '1' => '-1',
                '2' => (string) Fields::FIELD_EVENT_TITLE,
                '3' => (string) Fields::FIELD_EXTERNAL_ID,
                '4' => (string) Fields::FIELD_START_DATE,
                '5' => (string) Fields::FIELD_START_TIME,
                '6' => (string) Fields::FIELD_LOCATION,
            ],
        ],
        'role' => 'confirmation',
    ],
    'appointment_booking_confirmation' => [
        'template_name' => 'appointment_booking_confirmation_v2',
        'campaign_name' => 'Appointment booking confirmation',
        'category' => 'UTILITY',
        'language' => 'en',
        'body' => "Hello {{1}},\n\nYour appointment has been successfully booked.\n\n🔖 Reference: {{2}}\n📅 Date: {{3}}\n🕒 Time: {{4}}\n📍 Location: {{5}}\n\nKeep this reference to cancel or reschedule online, or contact us in advance.\n\nThank you, and we look forward to seeing you.",
        'example' => [
            'Jane Customer',
            '#42',
            'Aug 1, 2026',
            '10:00 AM',
            'Clinic A',
        ],
        'variables_match' => [
            'body' => [
                '1' => '-1',
                '2' => (string) Fields::FIELD_EXTERNAL_ID,
                '3' => (string) Fields::FIELD_START_DATE,
                '4' => (string) Fields::FIELD_START_TIME,
                '5' => (string) Fields::FIELD_LOCATION,
            ],
        ],
        'role' => 'confirmation',
    ],
    'event_reminder' => [
        'template_name' => 'event_reminder',
        'campaign_name' => 'Event reminder',
        'category' => 'UTILITY',
        'language' => 'en',
        'body' => "Hello {{1}},\n\nThis is a friendly reminder that *{{2}}* is happening soon.\n\n📅 Date: {{3}}\n🕒 Time: {{4}}\n📍 Venue: {{5}}\n\nWe look forward to seeing you there. Safe travels!",
        'example' => [
            'Jane Customer',
            'Annual Summit',
            'Aug 1, 2026',
            '10:00 AM',
            'Main Hall',
        ],
        'variables_match' => [
            'body' => [
                '1' => '-1',
                '2' => (string) Fields::FIELD_EVENT_TITLE,
                '3' => (string) Fields::FIELD_START_DATE,
                '4' => (string) Fields::FIELD_START_TIME,
                '5' => (string) Fields::FIELD_LOCATION,
            ],
        ],
        'role' => 'reminder_before',
    ],
    'appointment_reminder' => [
        'template_name' => 'appointment_reminder',
        'campaign_name' => 'Appointment reminder',
        'category' => 'UTILITY',
        'language' => 'en',
        'body' => "Hello {{1}},\n\nJust a reminder that your appointment is coming up.\n\n📅 Date: {{2}}\n🕒 Time: {{3}}\n📍 Location: {{4}}\n\nPlease arrive a few minutes early if possible.\n\nSee you soon!",
        'example' => [
            'Jane Customer',
            'Aug 1, 2026',
            '10:00 AM',
            'Clinic A',
        ],
        'variables_match' => [
            'body' => [
                '1' => '-1',
                '2' => (string) Fields::FIELD_START_DATE,
                '3' => (string) Fields::FIELD_START_TIME,
                '4' => (string) Fields::FIELD_LOCATION,
            ],
        ],
        'role' => 'reminder_before',
    ],
    'event_thank_you' => [
        'template_name' => 'event_thank_you',
        'campaign_name' => 'Event thank you',
        'category' => 'UTILITY',
        'language' => 'en',
        'body' => "Hello {{1}},\n\nThank you for attending *{{2}}*.\n\nWe truly appreciate your participation and hope you had a great experience.\n\nWe look forward to welcoming you again at future events!",
        'example' => [
            'Jane Customer',
            'Annual Summit',
        ],
        'variables_match' => [
            'body' => [
                '1' => '-1',
                '2' => (string) Fields::FIELD_EVENT_TITLE,
            ],
        ],
        'role' => 'reminder_after',
    ],
    'appointment_thank_you' => [
        'template_name' => 'appointment_thank_you',
        'campaign_name' => 'Appointment thank you',
        'category' => 'UTILITY',
        'language' => 'en',
        'body' => "Hello {{1}},\n\nThank you for visiting us today.\n\nWe appreciate the opportunity to serve you and hope your appointment met your expectations.\n\nWe look forward to seeing you again.",
        'example' => [
            'Jane Customer',
        ],
        'variables_match' => [
            'body' => [
                '1' => '-1',
            ],
        ],
        'role' => 'reminder_after',
    ],
];
