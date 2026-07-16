<?php

namespace Tests\Unit;

use App\Support\WhatsappFlowCategory;
use Tests\TestCase;

class WhatsappFlowCategoryTest extends TestCase
{
    public function test_normalizes_legacy_appointment_alias(): void
    {
        $this->assertSame('APPOINTMENT_BOOKING', WhatsappFlowCategory::normalize('APPOINTMENT'));
        $this->assertSame(['APPOINTMENT_BOOKING'], WhatsappFlowCategory::forMetaApi('APPOINTMENT'));
    }

    public function test_normalizes_feedback_to_survey(): void
    {
        $this->assertSame('SURVEY', WhatsappFlowCategory::normalize('FEEDBACK'));
    }

    public function test_keeps_valid_meta_categories(): void
    {
        foreach (WhatsappFlowCategory::ALLOWED as $category) {
            $this->assertSame($category, WhatsappFlowCategory::normalize($category));
        }
    }

    public function test_unknown_falls_back_to_other(): void
    {
        $this->assertSame('OTHER', WhatsappFlowCategory::normalize('NOT_A_REAL_CATEGORY'));
        $this->assertSame('OTHER', WhatsappFlowCategory::normalize(null));
        $this->assertSame('OTHER', WhatsappFlowCategory::normalize(''));
    }

    public function test_appointment_templates_use_meta_category(): void
    {
        foreach (['healthcare_appointment', 'appointment_booking', 'hospitality_booking'] as $key) {
            $this->assertSame(
                'APPOINTMENT_BOOKING',
                config("whatsapp-form-templates.{$key}.category")
            );
        }
    }
}
