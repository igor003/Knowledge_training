<?php

namespace App\Domain\User\Model;

use App\Domain\Shared\Model\BaseModel;
use App\Domain\Role\Model\Role;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

final class User extends BaseModel
{
    protected $table = 'users';

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'role_id' => 'integer',
        'active' => 'boolean',
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function roleModel(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function isAdmin(): bool
    {
        $role = $this->roleModel;

        return ($role?->active ?? false) && ($role->is_admin ?? false);
    }

    public function hasActiveRole(): bool
    {
        return $this->roleModel?->active ?? false;
    }

    public function getLastLoginAt(): ?Carbon
    {
        return $this->getAttribute('last_login_at');
    }

    public function roleLabel(string $locale): string
    {
        $role = $this->roleModel;

        if ($role instanceof Role) {
            return $role->label($locale);
        }

        return (string) $this->role;
    }
}
