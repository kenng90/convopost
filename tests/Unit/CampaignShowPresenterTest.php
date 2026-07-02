<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Services\Campaign\CampaignShowPresenter;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;
use Modules\Wpbox\Models\Template;
use Tests\TestCase;

class CampaignShowPresenterTest extends TestCase
{
    public function test_sms_campaign_uses_custom_template_label_and_sent_metrics(): void
    {
        $company = Company::factory()->create();

        $campaign = Campaign::create([
            'name' => 'SMS blast',
            'company_id' => $company->id,
            'channel' => Campaign::CHANNEL_SMS,
            'channel_template_key' => 'custom',
            'broadcast_type' => 'group',
            'variables' => json_encode(['sms_body' => 'Hello {{name}}']),
            'send_to' => 10,
            'sended_to' => 5,
        ]);

        $presenter = CampaignShowPresenter::for($campaign);

        $this->assertSame('SMS', $presenter->channelLabel());
        $this->assertSame(__('Custom message'), $presenter->templateLabel());
        $this->assertFalse($presenter->usesWhatsAppDeliveryMetrics());
        $this->assertSame('Hello {{name}}', $presenter->contentPreview()['body']);

        $analytics = $presenter->analytics();
        $this->assertFalse($analytics['show_delivered_metric']);
        $this->assertFalse($analytics['show_read_metric']);
    }

    public function test_whatsapp_campaign_shows_delivery_metrics(): void
    {
        $company = Company::factory()->create();

        $template = Template::create([
            'name' => 'hello_world',
            'language' => 'en',
            'status' => 'APPROVED',
            'company_id' => $company->id,
            'components' => json_encode([['type' => 'BODY', 'text' => 'Hi {{1}}']]),
            'category' => 'MARKETING',
        ]);

        $campaign = Campaign::create([
            'name' => 'WA blast',
            'company_id' => $company->id,
            'channel' => Campaign::CHANNEL_WHATSAPP,
            'template_id' => $template->id,
            'variables' => json_encode(['body' => ['1' => 'Jane']]),
            'send_to' => 4,
            'delivered_to' => 2,
            'read_by' => 1,
        ]);

        $presenter = CampaignShowPresenter::for($campaign);

        $this->assertSame('hello_world', $presenter->templateLabel());
        $this->assertTrue($presenter->usesWhatsAppDeliveryMetrics());
        $this->assertTrue($presenter->analytics()['show_delivered_metric']);
        $this->assertStringContainsString('Jane', $presenter->contentPreview()['components'][0]['text']);
    }

    public function test_email_message_row_includes_subject_and_status_label(): void
    {
        $company = Company::factory()->create();

        $campaign = Campaign::create([
            'name' => 'Email blast',
            'company_id' => $company->id,
            'channel' => Campaign::CHANNEL_EMAIL,
            'broadcast_type' => 'group',
            'variables' => json_encode([
                'email_subject' => 'Welcome',
                'email_body' => 'Thanks for joining',
            ]),
        ]);

        $contact = Contact::create([
            'company_id' => $company->id,
            'name' => 'Jane',
            'phone' => '+254700000001',
            'email' => 'jane@example.com',
            'subscribed' => 1,
        ]);

        $message = Message::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'company_id' => $company->id,
            'status' => Message::STATUS_SENT,
            'value' => 'Thanks for joining',
            'header_text' => 'Welcome',
        ]);

        $presenter = CampaignShowPresenter::for($campaign);
        $row = $presenter->messageRow($message);

        $this->assertSame('jane@example.com', $row['email']);
        $this->assertSame('Welcome', $row['subject']);
        $this->assertSame(__('Sent'), $row['status']);

        $report = $presenter->reportRow($message);
        $this->assertSame('jane@example.com', $report[1]);
        $this->assertSame('Welcome', $report[2]);
        $this->assertSame('SENT', $report[4]);
    }
}
