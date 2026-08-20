<?php

namespace App\Domain\Position\Model;

use App\Domain\Department\Model\Department;
use App\Domain\Competency\Model\CompetencyFunction;
use App\Domain\FunctionType\Model\FunctionType;
use App\Domain\Shared\Model\SimpleCatalogModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Position extends SimpleCatalogModel
{
    protected $table = 'factory_functions';

    public function functionType(): BelongsTo
    {
        return $this->belongsTo(FunctionType::class, 'factory_function_type_id');
    }

    public function functionTypeLabel(): string
    {
        return $this->functionType?->label() ?? '-';
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'factory_section_function', 'factory_function_id', 'factory_section_id')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    /**
     * @return array<int, int>
     */
    public function departmentIds(): array
    {
        return $this->departments
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function departmentLabels(): array
    {
        return $this->departments
            ->map(static fn (Department $department): string => $department->label())
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function departmentSortOrders(): array
    {
        return $this->departments
            ->mapWithKeys(static fn (Department $department): array => [
                (int) $department->id => (int) ($department->pivot?->sort_order ?? 0),
            ])
            ->all();
    }

    /** @return array<int, int> */
    public function factoryDepartmentIds(): array
    {
        return $this->departments
            ->pluck('factory_department_id')
            ->filter()
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function competencyAssignments(): HasMany
    {
        return $this->hasMany(CompetencyFunction::class, 'factory_function_id')
            ->with(['competency', 'department']);
    }

    /** @return array<int, array{factory_section_id:int, competency_id:int, critical:bool}> */
    public function competencyAssignmentData(): array
    {
        return $this->competencyAssignments
            ->map(static fn (CompetencyFunction $assignment): array => [
                'factory_section_id' => (int) $assignment->factory_section_id,
                'competency_id' => (int) $assignment->competency_id,
                'critical' => (bool) $assignment->critical,
            ])
            ->all();
    }
}
