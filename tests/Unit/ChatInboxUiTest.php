<?php

namespace Tests\Unit;

use Tests\TestCase;

class ChatInboxUiTest extends TestCase
{
    public function test_chat_master_view_loads_scoped_inbox_stylesheet(): void
    {
        $contents = file_get_contents(base_path('modules/Wpbox/Resources/views/chat/master.blade.php'));

        $this->assertStringContainsString('wpbox-inbox-v2', $contents);
        $this->assertStringContainsString('chat-inbox.css', $contents);
        $this->assertStringContainsString('id="chatList"', $contents);
    }

    public function test_chat_inbox_stylesheet_is_scoped_to_inbox_root(): void
    {
        $css = file_get_contents(public_path('custom/css/chat-inbox.css'));

        $this->assertStringContainsString('#chatList.wpbox-inbox-v2', $css);
        $this->assertStringContainsString('--wpbox-chat-accent: var(--primary', $css);
        $this->assertStringContainsString('.message-agent', $css);
        $this->assertStringContainsString('#chatMessages', $css);
        $this->assertStringContainsString('min-height: 0', $css);
        $this->assertStringContainsString('overflow-y: auto', $css);
    }
}
