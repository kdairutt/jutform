<?php

declare(strict_types=1);

namespace JutForm\Tests;

use JutForm\Tests\Support\IntegrationTestCase;

final class ReferenceTest extends IntegrationTestCase
{
    public function testFieldTypes(): void
    {
        $res = $this->get('/api/field-types');
        $this->assertSame(200, $res['status']);
        $body = $this->jsonBody($res);
        $this->assertNotEmpty($body['field_types']);
        $first = $body['field_types'][0];
        $this->assertArrayHasKey('slug', $first);
        $this->assertArrayHasKey('name', $first);
    }

    public function testCountries(): void
    {
        $res = $this->get('/api/countries');
        $this->assertSame(200, $res['status']);
        $body = $this->jsonBody($res);
        $this->assertNotEmpty($body['countries']);
        $codes = array_column($body['countries'], 'country_code');
        $this->assertContains('US', $codes);
    }
}
