<?php

namespace JutForm\Controllers;

use JutForm\Core\Request;
use JutForm\Core\RequestContext;
use JutForm\Core\Response;
use JutForm\Models\Form;
use JutForm\Models\Submission;
use JutForm\Services\PdfService;

class FeatureController
{
    public function exportPdf(Request $request, string $id): void
    {
        $uid = RequestContext::$currentUserId;
        if ($uid === null) {
            Response::error('Unauthorized', 401);
        }
        $form = Form::find((int) $id);
        if (!$form || (int) $form['user_id'] !== $uid) {
            Response::error('Not found', 404);
        }

        $submissions = Submission::findByForm((int) $id, 5000, 0);

        $rows = '';
        foreach ($submissions as $i => $sub) {
            $data = $sub['data_json'] ?? '{}';
            $decoded = json_decode((string) $data, true);
            $display = is_array($decoded)
                ? implode(', ', array_map(
                    static fn($k, $v) => $k . ': ' . (is_scalar($v) ? $v : json_encode($v)),
                    array_keys($decoded),
                    $decoded
                ))
                : $data;
            $rows .= '<tr><td>' . ($i + 1) . '</td><td>' . htmlspecialchars($display, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }

        $templatePath = dirname(__DIR__, 2) . '/resources/pdf-template.html';
        $html = PdfService::renderTemplate($templatePath, [
            'form_name' => htmlspecialchars((string) $form['title'], ENT_QUOTES, 'UTF-8'),
            'generated_at' => gmdate('Y-m-d H:i:s') . ' UTC',
            'submission_count' => (string) count($submissions),
            'rows' => $rows,
        ]);

        $pdf = PdfService::fromHtml($html);
        $filename = 'form-' . $id . '-submissions.pdf';
        Response::raw($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function createPayment(Request $request): void
    {
        $uid = \JutForm\Core\RequestContext::$currentUserId;
        if ($uid === null) {
            Response::error('Unauthorized', 401);
        }

        $body = $request->jsonBody();
        $amount = isset($body['amount']) ? (float) $body['amount'] : null;
        if ($amount === null || $amount <= 0) {
            Response::error('amount is required', 400);
        }

        $apiKey = \JutForm\Models\ConfigRepository::get('payment_api_key') ?? '';
        $gw = 'http://payment-gateway:8888';
        $headers = "Authorization: Bearer {$apiKey}\r\nAccept: application/json\r\n";

        // Step 1 — fetch salt
        $saltRaw = @file_get_contents($gw . '/salt', false, stream_context_create([
            'http' => ['method' => 'GET', 'header' => $headers, 'timeout' => 15],
        ]));
        if ($saltRaw === false) {
            Response::raw('{"error":"payment gateway unreachable"}', 503, ['Content-Type' => 'application/json']);
        }
        $saltData = json_decode((string) $saltRaw, true);
        $salt = (string) ($saltData['salt'] ?? '');

        // Step 2 — sign and charge
        $datetime = gmdate('Y-m-d H:i:s');
        $hash = hash('sha256', $uid . '|' . $amount . '|' . $datetime . $salt);

        $payload = json_encode(['hash' => $hash, 'user_id' => $uid, 'amount' => $amount, 'datetime' => $datetime]);
        $chargeRaw = @file_get_contents($gw . '/charge', false, stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => $headers . "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 15,
            ],
            'ssl' => ['ignore_errors' => true],
        ]));
        if ($chargeRaw === false) {
            Response::raw('{"error":"payment gateway unreachable"}', 503, ['Content-Type' => 'application/json']);
        }
        $result = json_decode((string) $chargeRaw, true);
        $status = (string) ($result['status'] ?? 'error');
        $txnId  = $result['transaction_id'] ?? null;

        \JutForm\Core\Database::getInstance()->prepare(
            'INSERT INTO payments (user_id, amount, transaction_id, status, gateway_hash, paid_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$uid, $amount, $txnId, in_array($status, ['approved','declined'], true) ? $status : 'error', $hash, $datetime]);

        if ($status === 'approved') {
            Response::json(['transaction_id' => $txnId, 'status' => 'approved']);
        } elseif ($status === 'declined') {
            Response::raw(
                json_encode(['status' => 'declined', 'reason' => $result['reason'] ?? '']),
                402,
                ['Content-Type' => 'application/json']
            );
        } else {
            Response::raw('{"error":"gateway error"}', 503, ['Content-Type' => 'application/json']);
        }
    }

    public function analyticsSummary(Request $request): void
    {
        $pdo = \JutForm\Core\Database::getInstance();

        $totals = $pdo->query(
            'SELECT SUM(views) AS total_views,
                    SUM(submissions) AS total_submissions,
                    ROUND(AVG(avg_fill_time), 2) AS avg_fill_time_seconds,
                    MAX(date) AS latest_entry_date
             FROM form_metrics'
        )->fetch(\PDO::FETCH_ASSOC);

        $peak = $pdo->query(
            'SELECT date AS peak_day, SUM(submissions) AS peak_day_submissions
             FROM form_metrics
             GROUP BY date
             ORDER BY peak_day_submissions DESC
             LIMIT 1'
        )->fetch(\PDO::FETCH_ASSOC);

        $countries = $pdo->query(
            'SELECT country_code, SUM(submissions) AS submissions
             FROM form_metrics
             GROUP BY country_code
             ORDER BY submissions DESC
             LIMIT 3'
        )->fetchAll(\PDO::FETCH_ASSOC);

        Response::json([
            'total_views'           => (int) ($totals['total_views'] ?? 0),
            'total_submissions'     => (int) ($totals['total_submissions'] ?? 0),
            'avg_fill_time_seconds' => round((float) ($totals['avg_fill_time_seconds'] ?? 0), 2),
            'peak_day'              => $peak['peak_day'] ?? null,
            'peak_day_submissions'  => (int) ($peak['peak_day_submissions'] ?? 0),
            'latest_entry_date'     => $totals['latest_entry_date'] ?? null,
            'top_countries'         => array_map(
                static fn($r) => ['country_code' => $r['country_code'], 'submissions' => (int) $r['submissions']],
                $countries
            ),
        ]);
    }
}
