<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Embedwhatsapp\Models\Whatsappwidget;
use Tests\TestCase;

class KnowledgeWhatsappWidgetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that WhatsApp widget script is included when company has a widget
     */
    public function test_whatsapp_widget_script_included_when_widget_exists(): void
    {
        // Create a user first
        $user = User::factory()->create();

        // Create a company manually
        $company = new Company();
        $company->name = 'Test Company';
        $company->subdomain = 'test-company';
        $company->user_id = $user->id;
        $company->save();

        // Create a WhatsApp widget for the company
        $widget = new Whatsappwidget();
        $widget->id = 'test123456';
        $widget->company_id = $company->id;
        $widget->phone_number = '+1234567890';
        $widget->header_text = 'Test Header';
        $widget->header_subtext = 'Online';
        $widget->widget_text = 'Hi there! How can I help?';
        $widget->button_text = 'Start Chat';
        $widget->widget_type = '1';
        $widget->button_color = '#14c656';
        $widget->header_color = '#006654';
        $widget->save();

        // Visit the knowledge base frontend
        $response = $this->get("/knowledge/{$company->subdomain}");

        $response->assertStatus(200);
        $response->assertSee('script src="'.config('app.url').'/popup/whatsapp?id=test123456"', false);
        $response->assertSee('<div id="embed-whatsapp-chat"></div>', false);
    }

    /**
     * Test that WhatsApp widget script is not included when company has no widget
     */
    public function test_whatsapp_widget_script_not_included_when_no_widget(): void
    {
        // Create a user first
        $user = User::factory()->create();

        // Create a company without a widget
        $company = new Company();
        $company->name = 'Test Company No Widget';
        $company->subdomain = 'test-company-no-widget';
        $company->user_id = $user->id;
        $company->save();

        // Visit the knowledge base frontend
        $response = $this->get("/knowledge/{$company->subdomain}");

        $response->assertStatus(200);
        $response->assertDontSee('/popup/whatsapp?id=');
        $response->assertDontSee('<div id="embed-whatsapp-chat"></div>');
    }
}
