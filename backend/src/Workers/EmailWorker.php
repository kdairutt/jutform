<?php

namespace JutForm\Workers;

use DateTimeImmutable;
use DateTimeZone;
use JutForm\Core\Database;
use JutForm\Models\KeyValueStore;
use JutForm\Services\SmtpMailer;

class EmailWorker
{
    public static function processBatch(): void
    {
        $pdo = Database::getInstance();
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $pdo->query(
            "SELECT id, recipient_email, subject, body FROM scheduled_emails
             WHERE status = 'pending' AND scheduled_at <= '{$now}'
             ORDER BY scheduled_at ASC LIMIT 25"
        );
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $claim = $pdo->prepare(
            "UPDATE scheduled_emails SET status = 'processing' WHERE id = ? AND status = 'pending'"
        );
        $finish = $pdo->prepare(
            'UPDATE scheduled_emails SET status = ?, sent_at = ? WHERE id = ?'
        );

        foreach ($rows as $row) {
            // Atomically claim the row; skip if another worker already took it.
            $claim->execute([(int) $row['id']]);
            if ($claim->rowCount() !== 1) {
                continue;
            }

            $ok = SmtpMailer::send(
                $row['recipient_email'],
                (string) $row['subject'],
                (string) $row['body']
            );
            $finish->execute([$ok ? 'sent' : 'failed', gmdate('Y-m-d H:i:s'), (int) $row['id']]);
        }
    }

    public static function handleSubmissionNotify(array $data): void
    {
        $formId = (int) ($data['form_id'] ?? 0);
        $submissionId = (int) ($data['submission_id'] ?? 0);
        if ($formId <= 0 || $submissionId <= 0) {
            return;
        }
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT id, user_id, title FROM forms WHERE id = ?');
        $stmt->execute([$formId]);
        $form = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$form) {
            return;
        }
        $owner = \JutForm\Models\User::find((int) $form['user_id']);
        if (!$owner) {
            return;
        }

        $subStmt = $pdo->prepare('SELECT data_json FROM submissions WHERE id = ?');
        $subStmt->execute([$submissionId]);
        $submission = $subStmt->fetch(\PDO::FETCH_ASSOC);
        $submissionData = [];
        if ($submission && !empty($submission['data_json'])) {
            $decoded = json_decode((string) $submission['data_json'], true);
            if (is_array($decoded)) {
                $submissionData = $decoded;
            }
        }

        $ins = $pdo->prepare(
            'INSERT INTO scheduled_emails (form_id, recipient_email, subject, body, scheduled_at, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $nowUtc = gmdate('Y-m-d H:i:s');

        // Owner receives a simple activity ping.
        $ins->execute([
            $formId,
            $owner['email'],
            'New submission',
            'Form ' . $formId . ' received submission ' . $submissionId,
            $nowUtc,
            'pending',
            $nowUtc,
        ]);

        // Additional notification recipients configured on the form receive the
        // customized notification email.
        $recipients = self::loadRecipients($formId);
        if (empty($recipients)) {
            return;
        }

        $template = KeyValueStore::get($formId, 'notification_email_template');
        $subject = 'New submission on ' . (string) $form['title'];
        $defaultBody = 'A new submission was received on ' . (string) $form['title'] . '.';
        $body = $template !== null && $template !== ''
            ? self::renderTemplate($template, $form, $submissionId, $submissionData)
            : $defaultBody;

        foreach ($recipients as $recipient) {
            $ins->execute([
                $formId,
                $recipient,
                $subject,
                $body,
                $nowUtc,
                'pending',
                $nowUtc,
            ]);
        }
    }

    /**
     * @return string[]
     */
    private static function loadRecipients(int $formId): array
    {
        $raw = KeyValueStore::get($formId, 'notification_emails');
        if ($raw === null || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $email) {
            if (is_string($email) && $email !== '') {
                $out[] = $email;
            }
        }
        return $out;
    }

    private static function renderTemplate(string $template, array $form, int $submissionId, array $submissionData): string
    {
        $vars = [
            'form_title' => (string) ($form['title'] ?? ''),
            'form_id' => (string) ($form['id'] ?? ''),
            'submission_id' => (string) $submissionId,
            'submitter_name' => self::guessSubmitterName($submissionData),
        ];
        foreach ($submissionData as $k => $v) {
            if (!is_string($k)) {
                continue;
            }
            if (is_scalar($v) || $v === null) {
                $vars[$k] = (string) $v;
            }
        }
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', static function ($m) use ($vars) {
            $key = $m[1];
            return array_key_exists($key, $vars) ? $vars[$key] : $m[0];
        }, $template) ?? $template;
    }

    private static function guessSubmitterName(array $submissionData): string
    {
        foreach (['submitter_name', 'name', 'full_name', 'first_name'] as $k) {
            if (isset($submissionData[$k]) && is_string($submissionData[$k]) && $submissionData[$k] !== '') {
                return $submissionData[$k];
            }
        }
        return 'there';
    }
}
