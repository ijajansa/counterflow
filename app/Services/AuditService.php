<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    public function record(?Business $business, ?User $actor, string $action, ?Model $subject = null, array $metadata = []): void
    {
        AuditLog::create([
            'business_id' => $business?->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata,
        ]);
    }
}
