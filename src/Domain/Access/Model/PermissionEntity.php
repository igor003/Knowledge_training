<?php

namespace App\Domain\Access\Model;

use App\Domain\Shared\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PermissionEntity extends BaseModel
{
    protected $table = 'permission_entities';

    protected $casts = [
        'active' => 'boolean',
        'supports_delete' => 'boolean',
        'is_system' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class, 'permission_entity_id');
    }

    public function label(string $locale): string
    {
        $field = 'name_' . $locale;
        $label = $this->getAttribute($field);

        if (is_string($label) && $label !== '') {
            return $label;
        }

        return (string) ($this->name_ru ?: $this->code);
    }
}
