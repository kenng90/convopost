<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class SafeRemoteUrl
{
    /**
     * @var list<string>
     */
    private const BLOCKED_HOSTS = [
        'localhost',
        'localhost.localdomain',
        'metadata.google.internal',
        'metadata.google.com',
        'instance-data',
    ];

    /**
     * Validate that a URL is a public http(s) endpoint and is not targeting
     * loopback, private, or link-local networks (SSRF protection).
     *
     * @throws InvalidArgumentException
     */
    public function assertPublicHttpUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '' || strlen($url) > 2048) {
            throw new InvalidArgumentException('Invalid URL.');
        }

        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new InvalidArgumentException('Invalid URL.');
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Only HTTP and HTTPS URLs are allowed.');
        }

        $host = strtolower(rtrim($parts['host'], '.'));

        if (in_array($host, self::BLOCKED_HOSTS, true) || str_ends_with($host, '.localhost')) {
            throw new InvalidArgumentException('URL host is not allowed.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if ($this->isBlockedIp($host)) {
                throw new InvalidArgumentException('URL host is not allowed.');
            }

            return $url;
        }

        $resolved = @gethostbynamel($host) ?: [];
        if ($resolved === []) {
            throw new InvalidArgumentException('URL host could not be resolved.');
        }

        foreach ($resolved as $ip) {
            if ($this->isBlockedIp($ip)) {
                throw new InvalidArgumentException('URL host is not allowed.');
            }
        }

        return $url;
    }

    public function isPublicHttpUrl(string $url): bool
    {
        try {
            $this->assertPublicHttpUrl($url);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Fetch a public HTTP URL with redirects disabled to prevent DNS-rebinding SSRF.
     *
     * @throws InvalidArgumentException|RuntimeException
     */
    public function fetch(string $url, int $maxBytes = 15_000_000): string
    {
        $url = $this->assertPublicHttpUrl($url);

        $response = Http::timeout(15)
            ->withOptions([
                'allow_redirects' => false,
                'http_errors' => false,
            ])
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('Remote URL could not be fetched.');
        }

        $body = $response->body();
        if (strlen($body) > $maxBytes) {
            throw new RuntimeException('Remote file exceeds the size limit.');
        }

        return $body;
    }

    private function isBlockedIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            if ($long === false) {
                return true;
            }

            $ranges = [
                ['0.0.0.0', '0.255.255.255'],
                ['10.0.0.0', '10.255.255.255'],
                ['100.64.0.0', '100.127.255.255'],
                ['127.0.0.0', '127.255.255.255'],
                ['169.254.0.0', '169.254.255.255'],
                ['172.16.0.0', '172.31.255.255'],
                ['192.0.0.0', '192.0.0.255'],
                ['192.168.0.0', '192.168.255.255'],
                ['198.18.0.0', '198.19.255.255'],
                ['224.0.0.0', '255.255.255.255'],
            ];

            foreach ($ranges as [$start, $end]) {
                if ($long >= ip2long($start) && $long <= ip2long($end)) {
                    return true;
                }
            }

            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);
            if ($packed === false) {
                return true;
            }

            // ::1 loopback, fc00::/7 unique local, fe80::/10 link-local
            if ($ip === '::1' || str_starts_with($ip, 'fe80:') || str_starts_with($ip, 'fc') || str_starts_with($ip, 'fd')) {
                return true;
            }

            $mapped = @inet_ntop(substr($packed, 12));
            if (str_starts_with($ip, '::ffff:') && is_string($mapped) && $this->isBlockedIp($mapped)) {
                return true;
            }
        }

        return ! (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
