<?php

namespace JutForm\Workers;

use JutForm\Models\FormResource;

class FormSetupWorker
{
    public static function handle(array $data): void
    {
        $formId = (int) ($data['form_id'] ?? 0);
        if ($formId <= 0) {
            return;
        }
        FormResource::ensureDefaults($formId);
    }
}
