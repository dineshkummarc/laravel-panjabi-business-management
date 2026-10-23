<?php

namespace App\Services;

class TenantContext
{
    private ?int $id = null;

    public function set(?int $id): void
    {
        $this->id = $id;
    }

    public function id(): ?int
    {
        return $this->id ?? auth()->user()?->organization_id;
    }
}
