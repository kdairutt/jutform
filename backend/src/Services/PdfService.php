<?php

namespace JutForm\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfService
{
    public static function fromHtml(string $html): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * @param array<string, string> $vars  Values are inserted as-is; callers must escape HTML where needed.
     */
    public static function renderTemplate(string $templatePath, array $vars): string
    {
        $html = (string) file_get_contents($templatePath);
        foreach ($vars as $key => $value) {
            $html = str_replace('{{' . $key . '}}', $value, $html);
        }
        return $html;
    }
}
