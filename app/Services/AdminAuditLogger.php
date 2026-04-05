<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AdminAuditLogger
{
    public function record(
        Request $request,
        string $action,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?array $before = null,
        ?array $after = null,
        ?string $description = null,
    ): void {
        AuditLog::query()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'description' => $description,
            'before' => $before,
            'after' => $after,
            'ip_address' => $request->ip(),
        ]);
    }
}
