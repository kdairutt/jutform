<?php

/**
 * Best-effort check for URLs that should not be used as webhooks (loopback, etc.).
 */
function isLocalRequest(string $url): bool
{
    $parts = parse_url($url);
    $host = $parts['host'] ?? '';
    $host = strtolower($host);
    if ($host === '127.0.0.1' || $host === 'localhost') {
        return true;
    }
    return false;
}

/**
 * True when the inbound HTTP request appears to originate from inside our
 * own network (service-to-service calls), false for traffic coming in from
 * the outside. The edge proxy sets X-Forwarded-For to the real client IP;
 * traffic from the public internet enters via the default gateway, so we
 * treat that address as external and anything else on the internal ranges
 * as internal.
 */
function isInternalRequest(): bool
{
    $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    $ip = '';
    if ($xff !== '') {
        $parts = array_map('trim', explode(',', $xff));
        $ip = $parts[0] ?? '';
    }
    if ($ip === '') {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    $gateway = defaultGatewayIp();
    if ($gateway !== null && $gateway === $ip) {
        return false;
    }
    if ($ip === '127.0.0.1' || $ip === '::1') {
        return true;
    }
    if (str_starts_with($ip, '172.') || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
        return true;
    }
    return false;
}

/**
 * Parses /proc/net/route and returns this container's default gateway IP
 * (the address external traffic arrives from once it's been routed into
 * our network). Returns null when the route table can't be read.
 */
function defaultGatewayIp(): ?string
{
    $content = @file_get_contents('/proc/net/route');
    if ($content === false) {
        return null;
    }
    $lines = explode("\n", $content);
    array_shift($lines);
    foreach ($lines as $line) {
        $parts = preg_split('/\s+/', trim($line));
        if (!is_array($parts) || count($parts) < 3) {
            continue;
        }
        if ($parts[1] !== '00000000') {
            continue;
        }
        $hex = $parts[2];
        if (strlen($hex) !== 8) {
            continue;
        }
        $bytes = [
            hexdec(substr($hex, 6, 2)),
            hexdec(substr($hex, 4, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 0, 2)),
        ];
        return implode('.', $bytes);
    }
    return null;
}
