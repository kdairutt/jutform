<?php

namespace JutForm\Controllers;

use JutForm\Core\Request;
use JutForm\Core\Response;

class FeatureController
{
    public function exportPdf(Request $request, string $id): void
    {
        Response::error('Not implemented', 501);
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
