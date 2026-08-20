<?php

namespace App\Domain\Audit\Model;

use App\Domain\Shared\Model\BaseModel;

final class AuditLog extends BaseModel
{
    protected $table = 'audit_logs';

    protected $casts = [
        'actor_user_id' => 'integer',
        'entity_id' => 'integer',
        'before_values' => 'array',
        'after_values' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
