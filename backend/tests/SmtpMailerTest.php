<?php

declare(strict_types=1);

namespace JutForm\Tests;

use JutForm\Services\SmtpMailer;
use PHPUnit\Framework\TestCase;

final class SmtpMailerTest extends TestCase
{
    private string $mailpitApi;

    protected function setUp(): void
    {
        parent::setUp();
        $mailHost = getenv('MAIL_HOST') ?: '127.0.0.1';
        $apiHost = getenv('MAILPIT_API_HOST') ?: ($mailHost === 'mailpit' ? 'mailpit' : '127.0.0.1');
        $apiPort = getenv('MAILPIT_API_PORT') ?: '8025';
        $this->mailpitApi = sprintf('http://%s:%s', $apiHost, $apiPort);

        if (!$this->mailpitReachable()) {
            self::markTestSkipped('Mailpit not reachable at ' . $this->mailpitApi);
        }

        $this->deleteAllMessages();
    }

    public function testSendsPlainTextWithCorrectContentType(): void
    {
        $ok = SmtpMailer::send('plain@example.com', 'Plain subject', "Hello there\nNo HTML here.");
        $this->assertTrue($ok);

        $raw = $this->latestRaw();
        $this->assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $raw);
        $this->assertStringContainsString('MIME-Version: 1.0', $raw);
    }

    public function testSendsHtmlBodyWithHtmlContentType(): void
    {
        $html = '<p>Hi <strong>Jane</strong>, thanks for submitting!</p>';
        $ok = SmtpMailer::send('html@example.com', 'Welcome', $html);
        $this->assertTrue($ok);

        $raw = $this->latestRaw();
        $this->assertStringContainsString('Content-Type: text/html; charset=UTF-8', $raw);
        $this->assertStringContainsString('<strong>Jane</strong>', $raw);
    }

    public function testTemplatePlaceholderSyntaxIsNotMistakenForHtml(): void
    {
        $text = 'Hi {{submitter_name}}, your receipt is attached.';
        $ok = SmtpMailer::send('plain@example.com', 'Receipt', $text);
        $this->assertTrue($ok);

        $raw = $this->latestRaw();
        $this->assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $raw);
    }

    private function mailpitReachable(): bool
    {
        $ctx = stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]]);
        $resp = @file_get_contents($this->mailpitApi . '/api/v1/messages?limit=1', false, $ctx);
        return $resp !== false;
    }

    private function deleteAllMessages(): void
    {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'DELETE',
                'timeout' => 2,
                'ignore_errors' => true,
            ],
        ]);
        @file_get_contents($this->mailpitApi . '/api/v1/messages', false, $ctx);
    }

    private function latestRaw(): string
    {
        $deadline = microtime(true) + 3.0;
        $ctx = stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]]);
        while (microtime(true) < $deadline) {
            $list = @file_get_contents($this->mailpitApi . '/api/v1/messages?limit=1', false, $ctx);
            if ($list !== false) {
                $decoded = json_decode($list, true);
                $messages = is_array($decoded) ? ($decoded['messages'] ?? []) : [];
                if (!empty($messages)) {
                    $id = $messages[0]['ID'] ?? null;
                    if (is_string($id) && $id !== '') {
                        $raw = @file_get_contents($this->mailpitApi . '/api/v1/message/' . $id . '/raw', false, $ctx);
                        if (is_string($raw) && $raw !== '') {
                            return $raw;
                        }
                    }
                }
            }
            usleep(100_000);
        }
        self::fail('Mailpit did not receive a message within the timeout');
    }
}
