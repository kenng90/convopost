<?php

namespace Tests\Unit;

use App\Services\Messaging\DTO\MessageContent;
use Illuminate\Http\Request;
use Modules\Tiktok\Messaging\TiktokWebhookParser;
use Tests\TestCase;

class TiktokWebhookParserTest extends TestCase
{
    public function test_parses_im_receive_msg_with_json_content_string(): void
    {
        $request = Request::create('/webhook', 'POST', [
            'event' => 'im_receive_msg',
            'user_openid' => 'biz-open-1',
            'create_time' => 1710000000,
            'content' => json_encode([
                'conversation_id' => 'cid-1',
                'message_id' => 'mid-tt-1',
                'sender' => 'user-open-9',
                'sender_nickname' => 'Ada',
                'message_type' => 'TEXT',
                'text' => ['body' => 'Hello from TikTok'],
                'timestamp' => 1710000000,
            ]),
        ]);

        $batch = (new TiktokWebhookParser)->parse($request);

        $this->assertFalse($batch->isStatusUpdate);
        $this->assertCount(1, $batch->messages);
        $message = $batch->messages[0];
        $this->assertSame('mid-tt-1', $message->externalMessageId);
        $this->assertSame('user-open-9', $message->externalParticipantId);
        $this->assertSame('Ada', $message->participantName);
        $this->assertSame('TEXT', $message->content->type);
        $this->assertSame('Hello from TikTok', $message->content->body);
        $this->assertSame('cid-1', $message->context['external_thread_id']);
        $this->assertSame('biz-open-1', $message->context['business_id']);
    }

    public function test_parses_image_message(): void
    {
        $request = Request::create('/webhook', 'POST', [
            'event' => 'im_receive_msg',
            'user_openid' => 'biz-open-1',
            'content' => [
                'conversation_id' => 'cid-2',
                'message_id' => 'mid-img-1',
                'sender' => 'user-open-2',
                'message_type' => 'IMAGE',
                'image' => ['url' => 'https://example.com/photo.jpg'],
            ],
        ]);

        $batch = (new TiktokWebhookParser)->parse($request);

        $this->assertCount(1, $batch->messages);
        $this->assertInstanceOf(MessageContent::class, $batch->messages[0]->content);
        $this->assertSame('IMAGE', $batch->messages[0]->content->type);
        $this->assertSame('https://example.com/photo.jpg', $batch->messages[0]->content->mediaUrl);
    }

    public function test_send_echo_is_status_update(): void
    {
        $request = Request::create('/webhook', 'POST', [
            'event' => 'im_send_msg',
            'user_openid' => 'biz-open-1',
            'content' => json_encode([
                'conversation_id' => 'cid-1',
                'message_id' => 'mid-echo',
            ]),
        ]);

        $batch = (new TiktokWebhookParser)->parse($request);

        $this->assertTrue($batch->isStatusUpdate);
        $this->assertSame([], $batch->messages);
    }

    public function test_parses_high_intent_comment(): void
    {
        $request = Request::create('/webhook', 'POST', [
            'event' => 'im_receive_high_intent_comment',
            'user_openid' => 'biz-open-1',
            'create_time' => 1710000000,
            'content' => json_encode([
                'comment_id' => 'cmt-hi-1',
                'video_id' => 'vid-99',
                'unique_identifier' => 'user-open-9',
                'sender_nickname' => 'Ada',
                'text' => ['body' => 'How much?'],
            ]),
        ]);

        $batch = (new TiktokWebhookParser)->parse($request);

        $this->assertCount(1, $batch->messages);
        $message = $batch->messages[0];
        $this->assertTrue($message->isComment());
        $this->assertSame('comment:cmt-hi-1', $message->externalMessageId);
        $this->assertSame('user-open-9', $message->externalParticipantId);
        $this->assertSame('How much?', $message->content->body);
        $this->assertTrue($message->context['high_intent']);
        $this->assertSame('cmt-hi-1', $message->context['comment_id']);
        $this->assertSame('vid-99', $message->context['media_id']);
    }

    public function test_parses_organic_comment_create(): void
    {
        $request = Request::create('/webhook', 'POST', [
            'event' => 'comment.create',
            'user_openid' => 'biz-open-1',
            'content' => [
                'comment_id' => 'cmt-org-1',
                'video_id' => 'vid-1',
                'user_id' => 'user-open-2',
                'text' => 'Nice video',
            ],
        ]);

        $batch = (new TiktokWebhookParser)->parse($request);

        $this->assertCount(1, $batch->messages);
        $this->assertTrue($batch->messages[0]->isComment());
        $this->assertFalse($batch->messages[0]->context['high_intent']);
        $this->assertSame('Nice video', $batch->messages[0]->content->body);
        $this->assertSame('user-open-2', $batch->messages[0]->externalParticipantId);
    }

    public function test_unknown_event_yields_empty_batch(): void
    {
        $request = Request::create('/webhook', 'POST', [
            'event' => 'video.publish',
        ]);

        $batch = (new TiktokWebhookParser)->parse($request);

        $this->assertFalse($batch->isStatusUpdate);
        $this->assertSame([], $batch->messages);
    }
}
