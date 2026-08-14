<?php

namespace Tests\Unit;

use App\Enums\MessagingChannelType;
use App\Services\Messaging\MetaMessagingParser;
use Illuminate\Http\Request;
use Tests\TestCase;

class MetaMessagingParserTest extends TestCase
{
    public function test_keeps_customer_message_even_if_echo_flag_set(): void
    {
        $request = Request::create('/webhook', 'POST', [
            'object' => 'instagram',
            'entry' => [[
                'id' => '17841401947499512',
                'messaging' => [[
                    'sender' => ['id' => 'ig-customer-1'],
                    'recipient' => ['id' => '1072030281944265'],
                    'timestamp' => 1710000000000,
                    'message' => [
                        'mid' => 'mid.KEEP_001',
                        'text' => 'Hello from customer',
                        'is_echo' => true,
                    ],
                ]],
            ]],
        ]);

        $batch = app(MetaMessagingParser::class)->parsePageMessaging($request, MessagingChannelType::Instagram);

        $this->assertCount(1, $batch->messages);
        $this->assertSame('ig-customer-1', $batch->messages[0]->externalParticipantId);
        $this->assertSame('Hello from customer', $batch->messages[0]->content->body);
    }

    public function test_skips_echo_sent_by_the_business_account(): void
    {
        $request = Request::create('/webhook', 'POST', [
            'object' => 'instagram',
            'entry' => [[
                'id' => '17841401947499512',
                'messaging' => [[
                    'sender' => ['id' => '17841401947499512'],
                    'recipient' => ['id' => 'ig-customer-1'],
                    'timestamp' => 1710000000000,
                    'message' => [
                        'mid' => 'mid.ECHO_001',
                        'text' => 'Business reply',
                        'is_echo' => true,
                    ],
                ]],
            ]],
        ]);

        $batch = app(MetaMessagingParser::class)->parsePageMessaging($request, MessagingChannelType::Instagram);

        $this->assertCount(0, $batch->messages);
    }

    public function test_skips_message_sent_by_page_even_without_echo_flag(): void
    {
        $request = Request::create('/webhook', 'POST', [
            'object' => 'instagram',
            'entry' => [[
                'id' => '17841401947499512',
                'messaging' => [[
                    'sender' => ['id' => '1072030281944265'],
                    'recipient' => ['id' => 'ig-customer-1'],
                    'timestamp' => 1710000000000,
                    'message' => [
                        'mid' => 'mid.PAGE_001',
                        'text' => 'From page',
                    ],
                ]],
            ]],
        ]);

        $connection = new \App\Models\Messaging\ChannelConnection([
            'external_account_id' => '1072030281944265',
            'credentials' => [
                'page_id' => '1072030281944265',
                'instagram_account_id' => '17841401947499512',
            ],
        ]);

        $batch = app(MetaMessagingParser::class)->parsePageMessaging(
            $request,
            MessagingChannelType::Instagram,
            $connection,
        );

        $this->assertCount(0, $batch->messages);
    }

    public function test_accepts_from_id_and_string_message(): void
    {
        $request = Request::create('/webhook', 'POST', [
            'object' => 'instagram',
            'entry' => [[
                'id' => '17841401947499512',
                'messaging' => [[
                    'from' => ['id' => 'ig-customer-2'],
                    'to' => ['id' => '17841401947499512'],
                    'timestamp' => 1710000000000,
                    'message' => 'Hi there',
                ]],
            ]],
        ]);

        $batch = app(MetaMessagingParser::class)->parsePageMessaging($request, MessagingChannelType::Instagram);

        $this->assertCount(1, $batch->messages);
        $this->assertSame('ig-customer-2', $batch->messages[0]->externalParticipantId);
        $this->assertSame('Hi there', $batch->messages[0]->content->body);
    }
}
