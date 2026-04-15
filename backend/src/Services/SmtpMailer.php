<?php

namespace JutForm\Services;

class SmtpMailer
{
    public static function send(string $to, string $subject, string $body): bool
    {
        $host = getenv('MAIL_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('MAIL_PORT') ?: 1025);
        $socket = @stream_socket_client($host . ':' . $port, $errno, $errstr, 10);
        if (!$socket) {
            return false;
        }
        $read = function () use ($socket): string {
            $data = '';
            while ($line = fgets($socket)) {
                $data .= $line;
                if (strlen($line) >= 4 && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $write = function (string $cmd) use ($socket): void {
            fwrite($socket, $cmd . "\r\n");
        };
        $read();
        $write('EHLO jutform.local');
        $read();
        $write('MAIL FROM:<noreply@jutform.local>');
        $read();
        $write('RCPT TO:<' . $to . '>');
        $read();
        $write('DATA');
        $read();
        $contentType = self::looksLikeHtml($body) ? 'text/html' : 'text/plain';
        $msg = "Subject: {$subject}\r\n";
        $msg .= "To: {$to}\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Content-Type: {$contentType}; charset=UTF-8\r\n\r\n";
        $msg .= str_replace("\r\n.", "\r\n..", str_replace("\n", "\r\n", $body)) . "\r\n";
        $write($msg . '.');
        $read();
        $write('QUIT');
        fclose($socket);
        return true;
    }

    private static function looksLikeHtml(string $body): bool
    {
        return (bool) preg_match('/<\s*[a-zA-Z][\s\S]*>/', $body);
    }
}
