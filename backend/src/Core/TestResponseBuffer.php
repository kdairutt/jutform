<?php

declare(strict_types=1);

namespace JutForm\Core;

/**
 * When {@see JUTFORM_TESTING} is true, {@see Response} records output here and throws
 * {@see ResponseHalted} instead of calling exit, so PHPUnit can assert on HTTP semantics.
 */
final class TestResponseBuffer
{
    /** @var array<string, mixed>|null */
    public static ?array $last = null;

    public static function reset(): void
    {
        self::$last = null;
    }

    public static function active(): bool
    {
        return defined('JUTFORM_TESTING') && JUTFORM_TESTING;
    }

    /**
     * @param array<string, mixed> $data Must include a 'type' key (json, html, csv, raw, file).
     */
    public static function capture(array $data): never
    {
        self::$last = $data;
        throw new ResponseHalted();
    }
}

final class ResponseHalted extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Response halted (test capture)');
    }
}
