<?php

declare(strict_types=1);

namespace App\Models;

use App\Lib\Db;

final class User
{
    /** Username for a given id, or null. */
    public static function name(int $id): ?string
    {
        $row = Db::run('SELECT username FROM users WHERE id = ?', [$id])->fetch();
        return $row ? $row['username'] : null;
    }
}
