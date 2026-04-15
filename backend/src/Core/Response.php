<?php

namespace JutForm\Core;

class Response
{
    public static function json(mixed $data, int $status = 200): void
    {
        if (TestResponseBuffer::active()) {
            TestResponseBuffer::capture([
                'type' => 'json',
                'status' => $status,
                'body' => $data,
            ]);
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $message, int $status = 400): void
    {
        self::json(['error' => $message], $status);
    }

    public static function html(string $html, int $status = 200): void
    {
        if (TestResponseBuffer::active()) {
            TestResponseBuffer::capture([
                'type' => 'html',
                'status' => $status,
                'body' => $html,
            ]);
        }
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        exit;
    }

    public static function raw(string $body, int $status = 200, array $headers = []): void
    {
        if (TestResponseBuffer::active()) {
            TestResponseBuffer::capture([
                'type' => 'raw',
                'status' => $status,
                'headers' => $headers,
                'body' => $body,
            ]);
        }
        http_response_code($status);
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $body;
        exit;
    }

    public static function fileStream(string $path, string $downloadName, string $contentType): void
    {
        if (!is_readable($path)) {
            self::error('File not found', 404);
        }
        if (TestResponseBuffer::active()) {
            TestResponseBuffer::capture([
                'type' => 'file',
                'status' => 200,
                'path' => $path,
                'downloadName' => $downloadName,
                'contentType' => $contentType,
            ]);
        }
        http_response_code(200);
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
        readfile($path);
        exit;
    }

    public static function inlineFile(string $path, string $contentType): void
    {
        if (!is_readable($path)) {
            self::error('File not found', 404);
        }
        if (TestResponseBuffer::active()) {
            TestResponseBuffer::capture([
                'type' => 'inline',
                'status' => 200,
                'path' => $path,
                'contentType' => $contentType,
            ]);
        }
        http_response_code(200);
        header('Content-Type: ' . $contentType);
        header('Cache-Control: no-store');
        readfile($path);
        exit;
    }

    public static function csv(string $filename, string $content): void
    {
        if (TestResponseBuffer::active()) {
            TestResponseBuffer::capture([
                'type' => 'csv',
                'status' => 200,
                'filename' => $filename,
                'body' => $content,
            ]);
        }
        http_response_code(200);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        echo $content;
        exit;
    }
}
