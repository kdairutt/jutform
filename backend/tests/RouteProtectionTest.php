<?php

declare(strict_types=1);

namespace JutForm\Tests;

use JutForm\Core\Request;
use JutForm\Tests\Support\IntegrationTestCase;

/**
 * Verifies baseline public and authenticated route access.
 */
final class RouteProtectionTest extends IntegrationTestCase
{
    public function testFieldTypesIsPublic(): void
    {
        $res = $this->get('/api/field-types');
        $this->assertSame(200, $res['status']);
        $body = $this->jsonBody($res);
        $this->assertArrayHasKey('field_types', $body);
    }

    public function testCountriesIsPublic(): void
    {
        $res = $this->get('/api/countries');
        $this->assertSame(200, $res['status']);
        $body = $this->jsonBody($res);
        $this->assertArrayHasKey('countries', $body);
    }

    public function testFormsListRequiresAuth(): void
    {
        $res = $this->get('/api/forms');
        $this->assertSame(401, $res['status']);
    }

    public function testPublicSubmissionCreateDoesNotRequireAuth(): void
    {
        $body = json_encode(['data' => ['hello' => 'world']], JSON_THROW_ON_ERROR);
        $req = Request::create('POST', '/api/forms/1/submissions', [], $body, [
            'Content-Type' => 'application/json',
        ]);
        $res = $this->dispatch($req);
        $this->assertSame('json', $res['type']);
        $this->assertSame(201, $res['status']);
    }

    public function testSearchRequiresAuth(): void
    {
        $res = $this->get('/api/search', ['q' => 'alpha']);
        $this->assertSame(401, $res['status']);
    }

    public function testFeatureRoutesRequireAuth(): void
    {
        $this->assertSame(401, $this->get('/api/forms/1/export/pdf')['status']);
        $this->assertSame(401, $this->postJson('/api/payments', [])['status']);
        $this->assertSame(401, $this->get('/api/analytics/summary')['status']);
    }
}
