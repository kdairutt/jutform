<?php

declare(strict_types=1);

namespace JutForm\Tests;

use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    public function testAutoload(): void
    {
        $this->assertTrue(class_exists(\JutForm\Core\Router::class));
    }
}
