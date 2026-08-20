<?php

namespace App\Domain\Shared\Model;

abstract class SimpleCatalogModel extends BaseModel
{
    protected $casts = [
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function label(): string
    {
        $label = $this->getAttribute('name');

        if (is_string($label) && $label !== '') {
            return $label;
        }

        return (string) $this->getAttribute('code');
    }
}
