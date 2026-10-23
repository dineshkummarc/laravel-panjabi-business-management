<?php

namespace App\Models;

class AuditLog extends BusinessModel
{
    protected function casts(): array
    {
        return ['details' => 'array'];
    }
}
