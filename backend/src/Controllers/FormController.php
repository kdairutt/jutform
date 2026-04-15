<?php

namespace JutForm\Controllers;

use JutForm\Core\Database;
use JutForm\Core\QueueService;
use JutForm\Core\Request;
use JutForm\Core\RequestContext;
use JutForm\Core\Response;
use JutForm\Models\Form;
use JutForm\Models\FormResource;
use JutForm\Models\KeyValueStore;
use JutForm\Models\Submission;
use JutForm\Models\User;
use JutForm\Services\ExternalApiService;

class FormController
{
    public function index(Request $request): void
    {
        $uid = RequestContext::$currentUserId;
        if ($uid === null) {
            Response::error('Unauthorized', 401);
        }
        $forms = Form::findByUser($uid);
        $out = [];
        foreach ($forms as $f) {
            $formId = (int) $f['id'];
            $count = Submission::countByForm($formId);
            $latest = Submission::getLatestSubmittedAt($formId);
            $owner = User::find((int) $f['user_id']);
            $out[] = [
                'id' => $formId,
                'title' => $f['title'],
                'status' => $f['status'],
                'submission_count' => $count,
                'last_submission_at' => $latest,
                'owner_display_name' => $owner['display_name'] ?? $owner['username'] ?? '',
            ];
        }
        Response::json(['forms' => $out]);
    }

    public function create(Request $request): void
    {
        $uid = RequestContext::$currentUserId;
        if ($uid === null) {
            Response::error('Unauthorized', 401);
        }
        $body = $request->jsonBody();
        $title = trim((string) ($body['title'] ?? ''));
        if ($title === '') {
            Response::error('title is required', 400);
        }
        $id = Form::create([
            'user_id' => $uid,
            'title' => $title,
            'description' => $body['description'] ?? '',
            'status' => $body['status'] ?? 'draft',
            'fields_json' => is_string($body['fields_json'] ?? null) ? $body['fields_json'] : json_encode($body['fields'] ?? []),
        ]);
        QueueService::dispatch('form_setup', ['form_id' => $id]);
        Response::json(['id' => $id, 'status' => 'created'], 201);
    }

    public function show(Request $request, string $id): void
    {
        $this->loadFormOrFail($id, false);
    }

    public function edit(Request $request, string $id): void
    {
        $this->loadFormOrFail($id, true);
    }

    private function loadFormOrFail(string $id, bool $editMode): void
    {
        $uid = RequestContext::$currentUserId;
        if ($uid === null) {
            Response::error('Unauthorized', 401);
        }
        $form = Form::find((int) $id);
        if (!$form || (int) $form['user_id'] !== $uid) {
            Response::error('Not found', 404);
        }
        $resources = FormResource::forForm((int) $id);
        if (count($resources) === 0) {
            Response::error('Form setup incomplete', 404);
        }
        $settings = KeyValueStore::allForForm((int) $id);
        $payload = [
            'form' => $form,
            'settings' => $settings,
            'resources' => $resources,
            'mode' => $editMode ? 'edit' : 'view',
        ];
        Response::json($payload);
    }

    public function update(Request $request, string $id): void
    {
        $uid = RequestContext::$currentUserId;
        if ($uid === null) {
            Response::error('Unauthorized', 401);
        }
        $form = Form::find((int) $id);
        if (!$form || (int) $form['user_id'] !== $uid) {
            Response::error('Not found', 404);
        }
        $body = $request->jsonBody();
        $update = [];
        foreach (['title', 'description', 'status', 'fields_json'] as $k) {
            if (array_key_exists($k, $body)) {
                $update[$k] = $body[$k];
            }
        }
        if ($update !== []) {
            Form::update((int) $id, $update);
        }
        if (isset($body['settings']) && is_array($body['settings'])) {
            foreach ($body['settings'] as $sk => $sv) {
                if (is_bool($sv)) {
                    KeyValueStore::set((int) $id, (string) $sk, $sv ? 'true' : 'false');
                } else {
                    KeyValueStore::set((int) $id, (string) $sk, is_string($sv) ? $sv : json_encode($sv));
                }
            }
        }
        Response::json(['ok' => true]);
    }

    public function analytics(Request $request): void
    {
        $uid = RequestContext::$currentUserId;
        if ($uid === null) {
            Response::error('Unauthorized', 401);
        }
        $data = ExternalApiService::fetchAnalyticsAggregate();
        Response::json(['analytics' => $data, 'user_id' => $uid]);
    }
}
