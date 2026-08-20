<?php

namespace App\Domain\FunctionType\Model;

use App\Domain\Position\Model\Position;
use App\Domain\Shared\Model\SimpleCatalogModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FunctionType extends SimpleCatalogModel
{
    protected $table = 'factory_function_types';

    public function functions(): HasMany
    {
        return $this->hasMany(Position::class, 'factory_function_type_id');
    }

    /** @return array<int, int> */
    public function functionIds(): array
    {
        return $this->functions->pluck('id')->map(static fn ($id): int => (int) $id)->all();
    }
}
