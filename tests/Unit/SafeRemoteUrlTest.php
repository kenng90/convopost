<?php

namespace Tests\Unit;

use App\Services\Security\SafeRemoteUrl;
use InvalidArgumentException;
use Tests\TestCase;

class SafeRemoteUrlTest extends TestCase
{
    private SafeRemoteUrl $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guard = new SafeRemoteUrl;
    }

    public function test_allows_public_https_url(): void
    {
        $this->assertTrue($this->guard->isPublicHttpUrl('https://8.8.8.8/media.jpg'));
    }

    public function test_rejects_loopback_and_private_addresses(): void
    {
        $this->assertFalse($this->guard->isPublicHttpUrl('http://127.0.0.1/secret'));
        $this->assertFalse($this->guard->isPublicHttpUrl('http://localhost/secret'));
        $this->assertFalse($this->guard->isPublicHttpUrl('http://169.254.169.254/latest/meta-data'));
        $this->assertFalse($this->guard->isPublicHttpUrl('http://10.0.0.8/internal'));
        $this->assertFalse($this->guard->isPublicHttpUrl('file:///etc/passwd'));
        $this->assertFalse($this->guard->isPublicHttpUrl('gopher://example.com'));
    }

    public function test_assert_throws_for_blocked_urls(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->guard->assertPublicHttpUrl('http://127.0.0.1/');
    }
}
