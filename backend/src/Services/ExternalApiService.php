<?php

namespace JutForm\Services;

class ExternalApiService
{
    public static function fetchAnalyticsAggregate(): array
    {
        $base = getenv('EXTERNAL_API_URL') ?: 'http://mock-api:8888';
        $url = rtrim($base, '/') . '/analytics';
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/json\r\n",
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            return ['error' => 'upstream_unavailable'];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : ['error' => 'invalid_json'];
    }
}
