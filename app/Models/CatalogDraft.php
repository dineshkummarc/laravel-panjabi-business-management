<?php

namespace App\Models;

class CatalogDraft extends BusinessModel
{
    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
