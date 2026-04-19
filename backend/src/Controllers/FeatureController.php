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
        Response::error('Not implemented', 501);
    }

    public function analyticsSummary(Request $request): void
    {
        Response::error('Not implemented', 501);
    }
}
