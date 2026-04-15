<?php

namespace JutForm\Models;

use JutForm\Core\Database;
use PDO;

class FieldTypeRepository
{
    public static function all(): array
    {
        $stmt = Database::getInstance()->query('SELECT slug, name FROM field_types ORDER BY sort_order, id');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
