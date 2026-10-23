<?php

namespace App\Services;

use App\Models\AuditLog;

class Audit
{
    public static function record(string $action, string $subject, array $details = []): void
    {
        AuditLog::create(['user_id' => auth()->id(), 'action' => $action, 'subject' => $subject, 'details' => $details]);
    }
}
