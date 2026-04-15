<?php

declare(strict_types=1);

namespace JutForm\Tests;

use JutForm\Core\Database;
use JutForm\Models\KeyValueStore;
use JutForm\Models\Submission;
use JutForm\Tests\Support\IntegrationTestCase;
use JutForm\Workers\EmailWorker;
use PDO;

final class EmailWorkerTest extends IntegrationTestCase
{
    private const FORM_ID = 1;

    private int $baselineEmailId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $pdo = Database::getInstance();
        $pdo->prepare('DELETE FROM form_settings WHERE form_id = ? AND setting_key IN (?, ?)')
            ->execute([self::FORM_ID, 'notification_emails', 'notification_email_template']);
        $this->baselineEmailId = (int) $pdo
            ->query('SELECT COALESCE(MAX(id), 0) FROM scheduled_emails')
            ->fetchColumn();
    }

    public function testOwnerEmailScheduledWhenNoRecipientsConfigured(): void
    {
        $submissionId = Submission::create(self::FORM_ID, json_encode(['submitter_name' => 'Alex']), '127.0.0.1');

        EmailWorker::handleSubmissionNotify([
            'form_id' => self::FORM_ID,
            'submission_id' => $submissionId,
        ]);

        $rows = $this->fetchScheduledFor($submissionId);
        $this->assertCount(1, $rows);
        $this->assertSame($this->ownerEmail(), $rows[0]['recipient_email']);
        $this->assertSame('New submission', $rows[0]['subject']);
        $this->assertStringContainsString((string) $submissionId, $rows[0]['body']);
    }

    public function testRecipientListReceivesTemplateBody(): void
    {
        KeyValueStore::set(self::FORM_ID, 'notification_emails', json_encode(['ops@example.com', 'alerts@example.com']));
        KeyValueStore::set(self::FORM_ID, 'notification_email_template', 'Hi {{submitter_name}}, thanks for submitting {{form_title}}.');

        $submissionId = Submission::create(self::FORM_ID, json_encode(['submitter_name' => 'Jane']), '127.0.0.1');

        EmailWorker::handleSubmissionNotify([
            'form_id' => self::FORM_ID,
            'submission_id' => $submissionId,
        ]);

        $rows = $this->fetchScheduledFor($submissionId);
        $this->assertCount(3, $rows, 'owner + 2 recipients should be scheduled');

        $byRecipient = [];
        foreach ($rows as $row) {
            $byRecipient[$row['recipient_email']] = $row;
        }

        $this->assertArrayHasKey($this->ownerEmail(), $byRecipient);
        $this->assertArrayHasKey('ops@example.com', $byRecipient);
        $this->assertArrayHasKey('alerts@example.com', $byRecipient);

        $form = $this->fetchForm(self::FORM_ID);
        $expectedBody = 'Hi Jane, thanks for submitting ' . $form['title'] . '.';
        $expectedSubject = 'New submission on ' . $form['title'];

        foreach (['ops@example.com', 'alerts@example.com'] as $email) {
            $this->assertSame($expectedSubject, $byRecipient[$email]['subject']);
            $this->assertSame($expectedBody, $byRecipient[$email]['body']);
        }

        $this->assertSame('New submission', $byRecipient[$this->ownerEmail()]['subject']);
    }

    public function testSubstitutesArbitrarySubmissionFieldKeys(): void
    {
        KeyValueStore::set(self::FORM_ID, 'notification_emails', json_encode(['ops@example.com']));
        KeyValueStore::set(self::FORM_ID, 'notification_email_template', 'Order {{order_ref}} from {{company}} ({{email}})');

        $submissionId = Submission::create(
            self::FORM_ID,
            json_encode([
                'order_ref' => 'A-42',
                'company' => 'Acme',
                'email' => 'buyer@example.com',
            ]),
            '127.0.0.1'
        );

        EmailWorker::handleSubmissionNotify([
            'form_id' => self::FORM_ID,
            'submission_id' => $submissionId,
        ]);

        $row = $this->fetchRowByRecipient($submissionId, 'ops@example.com');
        $this->assertNotNull($row);
        $this->assertSame('Order A-42 from Acme (buyer@example.com)', $row['body']);
    }

    public function testUnknownPlaceholdersArePreservedLiterally(): void
    {
        KeyValueStore::set(self::FORM_ID, 'notification_emails', json_encode(['ops@example.com']));
        KeyValueStore::set(self::FORM_ID, 'notification_email_template', 'Hello {{submitter_name}} — ref {{not_a_real_field}}');

        $submissionId = Submission::create(self::FORM_ID, json_encode(['submitter_name' => 'Jane']), '127.0.0.1');

        EmailWorker::handleSubmissionNotify([
            'form_id' => self::FORM_ID,
            'submission_id' => $submissionId,
        ]);

        $row = $this->fetchRowByRecipient($submissionId, 'ops@example.com');
        $this->assertNotNull($row);
        $this->assertSame('Hello Jane — ref {{not_a_real_field}}', $row['body']);
    }

    public function testFallbackBodyWhenNoTemplateConfigured(): void
    {
        KeyValueStore::set(self::FORM_ID, 'notification_emails', json_encode(['ops@example.com']));

        $submissionId = Submission::create(self::FORM_ID, json_encode(['submitter_name' => 'Jane']), '127.0.0.1');

        EmailWorker::handleSubmissionNotify([
            'form_id' => self::FORM_ID,
            'submission_id' => $submissionId,
        ]);

        $row = $this->fetchRowByRecipient($submissionId, 'ops@example.com');
        $this->assertNotNull($row);
        $form = $this->fetchForm(self::FORM_ID);
        $this->assertSame('A new submission was received on ' . $form['title'] . '.', $row['body']);
        $this->assertSame('New submission on ' . $form['title'], $row['subject']);
    }

    public function testEmptyRecipientListOnlyNotifiesOwner(): void
    {
        KeyValueStore::set(self::FORM_ID, 'notification_emails', json_encode([]));
        KeyValueStore::set(self::FORM_ID, 'notification_email_template', 'Unused');

        $submissionId = Submission::create(self::FORM_ID, json_encode([]), '127.0.0.1');

        EmailWorker::handleSubmissionNotify([
            'form_id' => self::FORM_ID,
            'submission_id' => $submissionId,
        ]);

        $rows = $this->fetchScheduledFor($submissionId);
        $this->assertCount(1, $rows);
        $this->assertSame($this->ownerEmail(), $rows[0]['recipient_email']);
    }

    public function testMalformedRecipientJsonIsIgnored(): void
    {
        KeyValueStore::set(self::FORM_ID, 'notification_emails', '["ops@example.com", not-json');

        $submissionId = Submission::create(self::FORM_ID, json_encode([]), '127.0.0.1');

        EmailWorker::handleSubmissionNotify([
            'form_id' => self::FORM_ID,
            'submission_id' => $submissionId,
        ]);

        $rows = $this->fetchScheduledFor($submissionId);
        $this->assertCount(1, $rows, 'only owner when recipient list is unparseable');
        $this->assertSame($this->ownerEmail(), $rows[0]['recipient_email']);
    }

    public function testNoOpWhenFormMissing(): void
    {
        EmailWorker::handleSubmissionNotify([
            'form_id' => 999999,
            'submission_id' => 1,
        ]);

        $pdo = Database::getInstance();
        $count = (int) $pdo->query('SELECT COUNT(*) FROM scheduled_emails WHERE form_id = 999999')->fetchColumn();
        $this->assertSame(0, $count);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function fetchScheduledFor(int $submissionId): array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT recipient_email, subject, body
             FROM scheduled_emails
             WHERE id > ? AND form_id = ?
             ORDER BY id ASC'
        );
        $stmt->execute([$this->baselineEmailId, self::FORM_ID]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchRowByRecipient(int $submissionId, string $recipient): ?array
    {
        foreach ($this->fetchScheduledFor($submissionId) as $row) {
            if ($row['recipient_email'] === $recipient) {
                return $row;
            }
        }
        return null;
    }

    private function fetchForm(int $formId): array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT id, title FROM forms WHERE id = ?');
        $stmt->execute([$formId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        return $row;
    }

    private function ownerEmail(): string
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT u.email FROM forms f INNER JOIN users u ON u.id = f.user_id WHERE f.id = ?');
        $stmt->execute([self::FORM_ID]);
        $email = $stmt->fetchColumn();
        $this->assertIsString($email);
        return (string) $email;
    }
}
