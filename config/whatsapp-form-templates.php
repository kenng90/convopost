<?php

/**
 * Starter WhatsApp Form templates for the form builder gallery.
 *
 * @return array<string, array<string, mixed>>
 */
return [
    'lead_capture' => [
        'name' => 'Lead Capture',
        'description' => 'Collect name, email, and interest in one screen.',
        'category' => 'LEAD_GENERATION',
        'screens' => [
            [
                'id' => 'LEAD_CAPTURE',
                'title' => 'Get in touch',
                'fields' => [
                    ['id' => 1, 'type' => 'heading', 'text' => 'Tell us about yourself'],
                    ['id' => 2, 'type' => 'text', 'label' => 'Full Name', 'required' => true, 'input_type' => 'text'],
                    ['id' => 3, 'type' => 'text', 'label' => 'Email', 'required' => true, 'input_type' => 'email'],
                    ['id' => 4, 'type' => 'text', 'label' => 'Phone', 'required' => false, 'input_type' => 'phone'],
                    ['id' => 5, 'type' => 'radio', 'label' => 'How can we help?', 'required' => true, 'options' => [
                        ['id' => 'opt_sales', 'title' => 'Sales enquiry'],
                        ['id' => 'opt_support', 'title' => 'Support'],
                        ['id' => 'opt_other', 'title' => 'Other'],
                    ]],
                    ['id' => 6, 'type' => 'footer', 'label' => 'Submit', 'action' => 'complete'],
                ],
            ],
        ],
    ],

    'feedback' => [
        'name' => 'Customer Feedback',
        'description' => 'Quick satisfaction survey with optional comments.',
        'category' => 'FEEDBACK',
        'screens' => [
            [
                'id' => 'FEEDBACK',
                'title' => 'Your feedback',
                'fields' => [
                    ['id' => 1, 'type' => 'heading', 'text' => 'How did we do?'],
                    ['id' => 2, 'type' => 'radio', 'label' => 'Overall rating', 'required' => true, 'options' => [
                        ['id' => '5', 'title' => 'Excellent'],
                        ['id' => '4', 'title' => 'Good'],
                        ['id' => '3', 'title' => 'Average'],
                        ['id' => '2', 'title' => 'Poor'],
                    ]],
                    ['id' => 3, 'type' => 'textarea', 'label' => 'Comments', 'required' => false],
                    ['id' => 4, 'type' => 'optin', 'label' => 'Contact me about this feedback', 'required' => false],
                    ['id' => 5, 'type' => 'footer', 'label' => 'Submit feedback', 'action' => 'complete'],
                ],
            ],
        ],
    ],

    'registration' => [
        'name' => 'Event Registration',
        'description' => 'Register attendees with date preference.',
        'category' => 'SIGN_UP',
        'screens' => [
            [
                'id' => 'REGISTER',
                'title' => 'Register',
                'fields' => [
                    ['id' => 1, 'type' => 'heading', 'text' => 'Event registration'],
                    ['id' => 2, 'type' => 'text', 'label' => 'Full Name', 'required' => true, 'input_type' => 'text'],
                    ['id' => 3, 'type' => 'text', 'label' => 'Company', 'required' => false, 'input_type' => 'text'],
                    ['id' => 4, 'type' => 'date', 'label' => 'Preferred date', 'required' => true],
                    ['id' => 5, 'type' => 'select', 'label' => 'Session', 'required' => true, 'options' => [
                        ['id' => 'morning', 'title' => 'Morning session'],
                        ['id' => 'afternoon', 'title' => 'Afternoon session'],
                    ]],
                    ['id' => 6, 'type' => 'footer', 'label' => 'Register', 'action' => 'complete'],
                ],
            ],
        ],
    ],

    'healthcare_appointment' => [
        'name' => 'Healthcare Appointment',
        'description' => 'Book a clinic visit with department selection.',
        'category' => 'APPOINTMENT',
        'industry' => 'healthcare',
        'automation_bundle' => 'healthcare_clinic_bot',
        'screens' => [
            [
                'id' => 'BOOK_VISIT',
                'title' => 'Book Your Visit',
                'fields' => [
                    ['id' => 1, 'type' => 'heading', 'text' => 'Book Your Visit'],
                    ['id' => 2, 'type' => 'text', 'label' => 'Full Name', 'required' => true, 'input_type' => 'text'],
                    ['id' => 3, 'type' => 'select', 'label' => 'Department', 'required' => true, 'options' => [
                        ['id' => 'general', 'title' => 'General Practice'],
                        ['id' => 'dental', 'title' => 'Dental'],
                        ['id' => 'lab', 'title' => 'Lab Tests'],
                    ]],
                    ['id' => 4, 'type' => 'date', 'label' => 'Preferred Date', 'required' => true],
                    ['id' => 5, 'type' => 'textarea', 'label' => 'Reason for visit', 'required' => false],
                    ['id' => 6, 'type' => 'footer', 'label' => 'Submit', 'action' => 'complete'],
                ],
            ],
        ],
    ],

    'real_estate_inquiry' => [
        'name' => 'Property Inquiry',
        'description' => 'Capture buyer/renter details and budget.',
        'category' => 'LEAD_GENERATION',
        'industry' => 'real_estate',
        'automation_bundle' => 'real_estate_agency_bot',
        'screens' => [
            [
                'id' => 'PROPERTY_INQUIRY',
                'title' => 'Property Inquiry',
                'fields' => [
                    ['id' => 1, 'type' => 'heading', 'text' => 'Tell us what you are looking for'],
                    ['id' => 2, 'type' => 'text', 'label' => 'Full Name', 'required' => true, 'input_type' => 'text'],
                    ['id' => 3, 'type' => 'radio', 'label' => 'Looking to', 'required' => true, 'options' => [
                        ['id' => 'buy', 'title' => 'Buy'],
                        ['id' => 'rent', 'title' => 'Rent'],
                    ]],
                    ['id' => 4, 'type' => 'text', 'label' => 'Budget range', 'required' => true, 'input_type' => 'text'],
                    ['id' => 5, 'type' => 'select', 'label' => 'Preferred area', 'required' => true, 'options' => [
                        ['id' => 'area_1', 'title' => 'City centre'],
                        ['id' => 'area_2', 'title' => 'Suburbs'],
                        ['id' => 'area_3', 'title' => 'Flexible'],
                    ]],
                    ['id' => 6, 'type' => 'footer', 'label' => 'Send inquiry', 'action' => 'complete'],
                ],
            ],
        ],
    ],

    'hospitality_booking' => [
        'name' => 'Hotel Booking Request',
        'description' => 'Pre-arrival guest details and preferences.',
        'category' => 'APPOINTMENT',
        'industry' => 'hospitality',
        'automation_bundle' => 'hotel_tour_concierge_bot',
        'screens' => [
            [
                'id' => 'BOOKING_REQUEST',
                'title' => 'Booking Request',
                'fields' => [
                    ['id' => 1, 'type' => 'heading', 'text' => 'Plan your stay'],
                    ['id' => 2, 'type' => 'text', 'label' => 'Guest Name', 'required' => true, 'input_type' => 'text'],
                    ['id' => 3, 'type' => 'date', 'label' => 'Check-in date', 'required' => true],
                    ['id' => 4, 'type' => 'date', 'label' => 'Check-out date', 'required' => true],
                    ['id' => 5, 'type' => 'select', 'label' => 'Room type', 'required' => true, 'options' => [
                        ['id' => 'standard', 'title' => 'Standard'],
                        ['id' => 'deluxe', 'title' => 'Deluxe'],
                        ['id' => 'suite', 'title' => 'Suite'],
                    ]],
                    ['id' => 6, 'type' => 'textarea', 'label' => 'Special requests', 'required' => false],
                    ['id' => 7, 'type' => 'footer', 'label' => 'Submit request', 'action' => 'complete'],
                ],
            ],
        ],
    ],

    'microfinance_loan_application' => [
        'name' => 'Loan Application',
        'description' => 'Capture personal, employment, and loan amount details.',
        'category' => 'SIGN_UP',
        'industry' => 'banking',
        'automation_bundle' => 'microfinance_banking_bot',
        'screens' => [
            [
                'id' => 'PERSONAL_DETAILS',
                'title' => 'Personal details',
                'fields' => [
                    ['id' => 1, 'type' => 'heading', 'text' => 'Personal information'],
                    ['id' => 2, 'type' => 'text', 'label' => 'Full Name', 'required' => true, 'input_type' => 'text'],
                    ['id' => 3, 'type' => 'text', 'label' => 'National ID', 'required' => true, 'input_type' => 'text'],
                    ['id' => 4, 'type' => 'text', 'label' => 'Phone', 'required' => true, 'input_type' => 'phone'],
                    ['id' => 5, 'type' => 'footer', 'label' => 'Continue', 'action' => 'navigate', 'next_screen' => 'EMPLOYMENT_INFO'],
                ],
            ],
            [
                'id' => 'EMPLOYMENT_INFO',
                'title' => 'Employment',
                'fields' => [
                    ['id' => 1, 'type' => 'heading', 'text' => 'Employment information'],
                    ['id' => 2, 'type' => 'text', 'label' => 'Employer', 'required' => true, 'input_type' => 'text'],
                    ['id' => 3, 'type' => 'text', 'label' => 'Monthly income', 'required' => true, 'input_type' => 'text'],
                    ['id' => 4, 'type' => 'footer', 'label' => 'Continue', 'action' => 'navigate', 'next_screen' => 'LOAN_AMOUNT'],
                ],
            ],
            [
                'id' => 'LOAN_AMOUNT',
                'title' => 'Loan amount',
                'fields' => [
                    ['id' => 1, 'type' => 'heading', 'text' => 'Loan request'],
                    ['id' => 2, 'type' => 'select', 'label' => 'Loan amount', 'required' => true, 'options' => [
                        ['id' => '50000', 'title' => 'KES 50,000'],
                        ['id' => '100000', 'title' => 'KES 100,000'],
                        ['id' => '250000', 'title' => 'KES 250,000'],
                    ]],
                    ['id' => 3, 'type' => 'select', 'label' => 'Repayment period', 'required' => true, 'options' => [
                        ['id' => '6', 'title' => '6 months'],
                        ['id' => '12', 'title' => '12 months'],
                        ['id' => '24', 'title' => '24 months'],
                    ]],
                    ['id' => 4, 'type' => 'optin', 'label' => 'I accept the loan terms and conditions', 'required' => true],
                    ['id' => 5, 'type' => 'footer', 'label' => 'Submit application', 'action' => 'complete'],
                ],
            ],
        ],
    ],
];
