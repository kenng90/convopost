<?php

namespace App\Services;

class VendorBrandingScrubber
{
    public static function companyName(): string
    {
        return config('settings.demo_company_name', config('settings.site_name', config('app.name', 'ConvoConnect')));
    }

    public static function contactName(): string
    {
        return config('settings.demo_contact_name', 'Kenneth');
    }

    public static function founderName(): string
    {
        return config('settings.demo_founder_name', 'Kenneth');
    }

    public static function appUrl(): string
    {
        return rtrim(config('app.url', 'https://convoconnect.io'), '/');
    }

    public static function supportEmail(): string
    {
        $host = parse_url(self::appUrl(), PHP_URL_HOST) ?: 'convoconnect.io';

        return 'support@'.$host;
    }

    /**
     * @return array<int, array{key: string, value: string}>
     */
    public static function emailTemplateConfigs(): array
    {
        $company = self::companyName();
        $founder = self::founderName();
        $url = self::appUrl();
        $support = self::supportEmail();

        return [
            ['key' => 'EMAIL_TEMPLATE_1_SUBJECT', 'value' => "Welcome to {$company} – Let's Get Started!"],
            ['key' => 'EMAIL_TEMPLATE_1_BODY', 'value' => "Hi {name},  \r\n\r\nWelcome to {$company}! We're thrilled to have you on board.  \r\n\r\nOur mission is to help businesses like yours grow with innovative tools and strategies.  \r\n\r\nHere's how to get started:  \r\n1. Explore our features tailored to your needs.  \r\n2. Schedule a free demo with our team.  \r\n3. Check out our knowledge base for quick tips.  \r\n\r\n👉 [Get Started Now]({$url})  \r\n\r\nNeed help? Our support team is here for you at [{$support}].  \r\n\r\nCheers,  \r\n{$founder}  \r\nFounder, {$company}"],
            ['key' => 'EMAIL_TEMPLATE_2_SUBJECT', 'value' => "{name}, Unlock Your Full Potential with {$company}"],
            ['key' => 'EMAIL_TEMPLATE_2_BODY', 'value' => "Hi {name},  \r\n\r\nWe noticed you're exploring {$company}, and we wanted to share how others like you are achieving amazing results:  \r\n\r\n- **[Case Study 1]:** Increased customer engagement by 40% in just 2 months.  \r\n- **[Case Study 2]:** Streamlined marketing workflows, saving 15+ hours weekly.  \r\n\r\nReady to see what {$company} can do for you?  \r\n\r\n👉 [Schedule Your Free Demo]({$url})  \r\n\r\nLet's work together to transform your business today!  \r\n\r\nBest regards,  \r\n{$founder}  \r\nFounder, {$company}"],
            ['key' => 'EMAIL_TEMPLATE_3_SUBJECT', 'value' => "{name}, Let's Reignite Your Success with {$company}"],
            ['key' => 'EMAIL_TEMPLATE_3_BODY', 'value' => "Hi {name},  \r\n\r\nWe noticed you haven't been active recently, and we'd love to help you get back on track!  \r\n\r\nHere's what's new at {$company}:  \r\n- **Enhanced Features:** Boost productivity with our latest updates.  \r\n- **Customer Success Stories:** Learn how businesses like yours are thriving.  \r\n\r\nLet's reconnect and explore how {$company} can help you achieve your goals.  \r\n\r\n👉 [Book a Call Now]({$url})  \r\n\r\nYour success is our priority. Let us know how we can assist you!  \r\n\r\nWarm regards,  \r\n{$founder}  \r\nFounder, {$company}"],
        ];
    }

    /**
     * @return array<int, array{key: string, value: string}>
     */
    public static function smsTemplateConfigs(): array
    {
        $company = self::companyName();
        $url = self::appUrl();

        return [
            ['key' => 'SMS_TEMPLATE_1', 'value' => "Welcome to {$company}, {name}! We're thrilled to have you on board. Explore our features and get started today: {$url}"],
            ['key' => 'SMS_TEMPLATE_2', 'value' => "Hi {name}, ready to grow your business? Discover how {$company} can help you achieve your goals. Schedule a free demo now: {$url}"],
            ['key' => 'SMS_TEMPLATE_3', 'value' => "Hi {name}, we miss you at {$company}! Check out what's new and let's reconnect: {$url}"],
            ['key' => 'SMS_TEMPLATE_4', 'value' => "Hi {name}, exclusive offer just for you! Get 20% off your next subscription with {$company}. Claim now: {$url}"],
        ];
    }

    public static function scrub(string $value): string
    {
        $company = self::companyName();
        $contact = self::contactName();
        $founder = self::founderName();
        $url = self::appUrl();
        $host = parse_url($url, PHP_URL_HOST) ?: 'convoconnect.io';

        $replacements = [
            'Daniel Dimov' => $contact,
            'daniel@mobidonia.com' => strtolower($contact).'@'.$host,
            'aleks@mobidonia.com' => 'contact2@'.$host,
            'support@mobidonia.com' => self::supportEmail(),
            'Founder, Mobidonia' => "Founder, {$company}",
            'Daniel is looking for pizza' => "{$contact} is looking for pizza",
            'https://mobidonia.com/demo' => $url,
            'https://mobidonia.com/call' => $url,
            'https://mobidonia.com/offer' => $url,
            'https://mobidonia.com' => $url,
            'mobidonia.com' => $host,
            'Mobidonia' => $company,
            'Jane Smith' => $contact,
            'Demo Restaurant' => $company,
            '"Daniel"' => '"'.$contact.'"',
            '"Jane"' => '"'.$contact.'"',
            'Daniel' => $founder,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $value);
    }

    public static function containsVendorBranding(string $value): bool
    {
        return str_contains($value, 'Mobidonia')
            || str_contains($value, 'mobidonia')
            || str_contains($value, 'Daniel Dimov')
            || str_contains($value, 'daniel@mobidonia.com')
            || str_contains($value, 'aleks@mobidonia.com')
            || preg_match('/\bDaniel\b/', $value) === 1;
    }
}
