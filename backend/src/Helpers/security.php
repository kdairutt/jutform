<?php

/**
 * Returns true if the URL targets a private/internal address (loopback, RFC-1918,
 * link-local, metadata service). Used to block SSRF via webhook URLs.
 */
function isLocalRequest(string $url): bool
{
    $parts = parse_url($url);
    $host = strtolower(trim($parts['host'] ?? ''));
    if ($host === '') {
        return true;
    }

    // Resolve hostname to an IP so DNS-based bypasses are also caught.
    $resolved = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

    // gethostbyname returns the original string on failure — treat as blocked.
    if (!filter_var($resolved, FILTER_VALIDATE_IP)) {
        return true;
    }

    // IPv6 loopback
    if ($resolved === '::1') {
        return true;
    }

    // Convert to a 32-bit integer for range comparisons.
    $long = ip2long($resolved);
    if ($long === false) {
        return true;
    }

    $blocked = [
        ['0.0.0.0',        '0.255.255.255'],   // current network
        ['10.0.0.0',       '10.255.255.255'],   // RFC-1918
        ['100.64.0.0',     '100.127.255.255'],  // shared address space
        ['127.0.0.0',      '127.255.255.255'],  // loopback
        ['169.254.0.0',    '169.254.255.255'],  // link-local / cloud metadata
        ['172.16.0.0',     '172.31.255.255'],   // RFC-1918
        ['192.168.0.0',    '192.168.255.255'],  // RFC-1918
        ['198.18.0.0',     '198.19.255.255'],   // benchmark testing
        ['198.51.100.0',   '198.51.100.255'],   // documentation (TEST-NET-2)
        ['203.0.113.0',    '203.0.113.255'],    // documentation (TEST-NET-3)
        ['240.0.0.0',      '255.255.255.255'],  // reserved
    ];

    foreach ($blocked as [$start, $end]) {
        if ($long >= ip2long($start) && $long <= ip2long($end)) {
            return true;
        }
    }

    return false;
}

/**
 * True when the TCP connection originates from inside our own network
 * (service-to-service calls). Uses REMOTE_ADDR only — X-Forwarded-For is
 * client-controlled and must never be trusted for access-control decisions.
 */
function isInternalRequest(): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip === '127.0.0.1' || $ip === '::1') {
        return true;
    }
    $long = ip2long($ip);
    if ($long === false) {
        return false;
    }
    $internal = [
        ['10.0.0.0', '10.255.255.255'],
        ['172.16.0.0', '172.31.255.255'],
        ['192.168.0.0', '192.168.255.255'],
    ];
    foreach ($internal as [$start, $end]) {
        if ($long >= ip2long($start) && $long <= ip2long($end)) {
            return true;
        }
    }
    return false;
}

