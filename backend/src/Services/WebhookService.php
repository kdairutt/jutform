<?php

namespace JutForm\Services;

class WebhookService
{
    public static function fire(string $url, array $payload, string $method = 'POST'): array
    {
        if (\isLocalRequest($url)) {
            return ['ok' => false, 'error' => 'local_urls_not_allowed'];
        }
        $method = strtoupper($method);
        $httpOpts = [
            'method' => $method,
            'header' => "Accept: application/json\r\n",
            'timeout' => 20,
        ];
        if (!in_array($method, ['GET', 'HEAD', 'DELETE'], true)) {
            $httpOpts['content'] = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $httpOpts['header'] = "Content-Type: application/json\r\nAccept: application/json\r\n";
        }
        $ctx = stream_context_create(['http' => $httpOpts]);
        $resp = @file_get_contents($url, false, $ctx);
        $code = 0;
        if (isset($http_response_header[0]) && preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $m)) {
            $code = (int) $m[1];
        }
        return ['status_code' => $code, 'body' => $resp !== false ? (string) $resp : ''];
    }
}
