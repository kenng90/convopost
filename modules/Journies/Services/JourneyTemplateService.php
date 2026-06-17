<?php

namespace Modules\Journies\Services;

use Modules\Journies\Models\Journey;
use Modules\Journies\Models\JourneyStage;

class JourneyTemplateService
{
    /**
     * @return array<string, array{name: string, description: string, stages: array<int, string>}>
     */
    public function templates(): array
    {
        return [
            'sales' => [
                'name' => __('Sales Pipeline'),
                'description' => __('Track leads from first contact through to closed deals.'),
                'stages' => [
                    __('Lead'),
                    __('Qualified'),
                    __('Proposal'),
                    __('Won'),
                ],
            ],
            'support' => [
                'name' => __('Support Pipeline'),
                'description' => __('Manage customer issues from intake to resolution.'),
                'stages' => [
                    __('New Ticket'),
                    __('In Progress'),
                    __('Waiting on Customer'),
                    __('Resolved'),
                ],
            ],
            'marketing' => [
                'name' => __('Marketing Funnel'),
                'description' => __('Nurture prospects through awareness to advocacy.'),
                'stages' => [
                    __('Subscriber'),
                    __('Engaged'),
                    __('Opportunity'),
                    __('Customer'),
                ],
            ],
            'onboarding' => [
                'name' => __('Customer Onboarding'),
                'description' => __('Guide new customers through setup and activation.'),
                'stages' => [
                    __('Welcome'),
                    __('Setup'),
                    __('Training'),
                    __('Active'),
                ],
            ],
            'revenue' => [
                'name' => __('Revenue Pipeline'),
                'description' => __('Track deals from lead to payment — ideal for WhatsApp sales teams.'),
                'stages' => [
                    __('New Lead'),
                    __('Qualified'),
                    __('Proposal'),
                    __('Paid'),
                    __('Onboarded'),
                ],
            ],
            'ecommerce' => [
                'name' => __('E-commerce Orders'),
                'description' => __('Manage catalog orders from inquiry through delivery.'),
                'stages' => [
                    __('Inquiry'),
                    __('Quoted'),
                    __('Awaiting Payment'),
                    __('Paid'),
                    __('Fulfilled'),
                ],
            ],
            'events' => [
                'name' => __('Event Registrations'),
                'description' => __('Move registrants from interest to attendance.'),
                'stages' => [
                    __('Interested'),
                    __('Registered'),
                    __('Confirmed'),
                    __('Attended'),
                ],
            ],
        ];
    }

    public function createFromTemplate(string $key): ?Journey
    {
        $templates = $this->templates();

        if (! isset($templates[$key])) {
            return null;
        }

        $template = $templates[$key];

        $journey = Journey::create([
            'name' => $template['name'],
            'description' => $template['description'],
        ]);

        foreach ($template['stages'] as $index => $stageName) {
            JourneyStage::create([
                'journey_id' => $journey->id,
                'name' => $stageName,
                'order' => $index,
                'campaign_id' => null,
                'campaign_delay_minutes' => 0,
            ]);
        }

        return $journey->load('stages');
    }
}
