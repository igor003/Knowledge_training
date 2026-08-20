<?php

namespace App\Domain\Access\Model;

use App\Domain\Role\Model\Role;
use App\Domain\Shared\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RolePermission extends BaseModel
{
    public const ACTION_READ = 'read';
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DEACTIVATE = 'deactivate';
    public const ACTION_DELETE = 'delete';

    protected $table = 'role_permissions';

    protected $casts = [
        'role_id' => 'integer',
        'permission_entity_id' => 'integer',
        'can_read' => 'boolean',
        'can_create' => 'boolean',
        'can_update' => 'boolean',
        'can_deactivate' => 'boolean',
        'can_delete' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(PermissionEntity::class, 'permission_entity_id');
    }

    public function allows(string $action): bool
    {
        return match ($action) {
            self::ACTION_READ => $this->can_read,
            self::ACTION_CREATE => $this->can_create,
            self::ACTION_UPDATE => $this->can_update,
            self::ACTION_DEACTIVATE => $this->can_deactivate,
            self::ACTION_DELETE => $this->can_delete,
            default => false,
        };
    }
}
