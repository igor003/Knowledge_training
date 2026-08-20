<?php

namespace App\Domain\Role\Model;

use App\Domain\Shared\Model\BaseModel;
use App\Domain\User\Model\User;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Role extends BaseModel
{
    public const DEFAULT_ADMIN_CODE = 'admin';
    public const DEFAULT_MANAGER_CODE = 'manager';

    protected $table = 'roles';

    protected $casts = [
        'is_admin' => 'boolean',
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function label(string $locale): string
    {
        $label = $this->getAttribute('name');

        if (is_string($label) && $label !== '') {
            return $label;
        }

        return (string) ($this->name_ru ?: $this->code);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }
}
