<?php

declare(strict_types=1);

namespace JutForm\Tests;

use JutForm\Tests\Support\IntegrationTestCase;

/**
 * Keep this suite focused on representative search requests.
 */
final class SearchTest extends IntegrationTestCase
{
    public function testEmptyQueryReturnsEmpty(): void
    {
        $this->loginAs('alice');
        $res = $this->get('/api/search', ['q' => '']);
        $this->assertSame(200, $res['status']);
        $body = $this->jsonBody($res);
        $this->assertSame([], $body['results']);
    }

    public function testBasicSearchReturnsJson(): void
    {
        $this->loginAs('alice');
        $res = $this->get('/api/search', ['q' => 'alpha']);
        $this->assertSame(200, $res['status']);
        $body = $this->jsonBody($res);
        $this->assertArrayHasKey('results', $body);
        $this->assertIsArray($body['results']);
    }

    public function testAdvancedSearchSafeTitleTerm(): void
    {
        $this->loginAs('poweruser');
        $res = $this->get('/api/search/advanced', [
            'field' => 'title',
            'term' => 'Power form',
        ]);
        $this->assertSame(200, $res['status']);
        $body = $this->jsonBody($res);
        $this->assertArrayHasKey('forms', $body);
        $this->assertIsArray($body['forms']);
    }
}
