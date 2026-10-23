<?php

namespace App\Models;

class JournalEntry extends BusinessModel
{
    protected function casts(): array
    {
        return [];
    }

    public function lines()
    {
        return $this->hasMany(JournalLine::class);
    }
}
