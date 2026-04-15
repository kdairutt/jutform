<?php

namespace JutForm\Models;

use JutForm\Core\Database;
use PDO;

class CountryRepository
{
    public static function all(): array
    {
        $stmt = Database::getInstance()->query('SELECT code AS country_code, name FROM countries ORDER BY name');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
