<?php

namespace App\Domain\Department\Model;

use App\Domain\FactoryDepartment\Model\FactoryDepartment;
use App\Domain\Position\Model\Position;
use App\Domain\Shared\Model\SimpleCatalogModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Department extends SimpleCatalogModel
{
    protected $table = 'factory_sections';

    public function factoryDepartment(): BelongsTo
    {
        return $this->belongsTo(FactoryDepartment::class, 'factory_department_id');
    }

    public function positions(): BelongsToMany
    {
        return $this->belongsToMany(Position::class, 'factory_section_function', 'factory_section_id', 'factory_function_id')
            ->withPivot('sort_order')
            ->orderBy('factory_section_function.sort_order')
            ->orderBy('factory_functions.name')
            ->orderBy('factory_functions.id')
            ->withTimestamps();
    }

    /**
     * @return array<int, int>
     */
    public function positionIds(): array
    {
        return $this->positions
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function positionLabels(): array
    {
        return $this->positions
            ->map(static fn (Position $position): string => $position->label())
            ->all();
    }

    public function factoryDepartmentLabel(): string
    {
        $department = $this->factoryDepartment;

        if ($department instanceof FactoryDepartment) {
            return $department->label();
        }

        return '-';
    }
}
