<?php

namespace Tests\Unit;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Social\Support\SocialBrand;
use Tests\TestCase;

class SocialBrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_product_name_uses_unganisha_social(): void
    {
        config(['settings.site_name' => 'ConvoConnect']);

        $this->assertSame('Unganisha Social', SocialBrand::productName());
        $this->assertSame('Unganisha', SocialBrand::platformName());
        $this->assertFalse(SocialBrand::usesCustomDomain());
    }

    public function test_white_label_site_name_renames_social_product(): void
    {
        config(['settings.site_name' => 'Acme Agency']);

        $this->assertSame('Acme Agency Social', SocialBrand::productName());
        $this->assertSame('Acme Agency', SocialBrand::platformName());
    }

    public function test_company_custom_domain_uses_company_branding(): void
    {
        config(['settings.site_name' => 'Unganisha']);

        $company = Company::factory()->create(['name' => 'Northwind Retail']);
        $company->setConfig('domain', 'social.northwind.test');

        $this->assertTrue(SocialBrand::usesCustomDomain($company));
        $this->assertSame('Northwind Retail Social', SocialBrand::productName($company));
        $this->assertSame('Northwind Retail', SocialBrand::platformName($company));
    }
}
