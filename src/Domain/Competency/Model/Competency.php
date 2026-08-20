<?php

namespace App\Domain\Competency\Model;

use App\Domain\Department\Model\Department;
use App\Domain\Position\Model\Position;
use App\Domain\Shared\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Competency extends BaseModel
{
    public const TYPE_SKILL = 'skill';
    public const TYPE_KNOWLEDGE = 'knowledge';

    protected $table = 'competencies';

    protected $casts = [
        'factory_section_id' => 'integer',
        'factory_function_id' => 'integer',
        'critical' => 'boolean',
        'minimum_score' => 'integer',
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function functionAssignments(): HasMany
    {
        return $this->hasMany(CompetencyFunction::class, 'competency_id')
            ->with(['department.factoryDepartment', 'function']);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'factory_section_id');
    }

    public function function(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'factory_function_id');
    }

    public function label(): string
    {
        return (string) $this->getAttribute('name');
    }

    /** @return array<int, string> */
    public function functionPairLabels(): array
    {
        return $this->functionAssignments
            ->map(static fn (CompetencyFunction $assignment): string => $assignment->department->factoryDepartmentLabel().' / '.$assignment->department->label().' / '.$assignment->function->label())
            ->all();
    }

    /** @return array<int, array{label:string, critical:bool}> */
    public function functionPairDisplayData(): array
    {
        return $this->functionAssignments
            ->map(static fn (CompetencyFunction $assignment): array => [
                'label' => $assignment->department->factoryDepartmentLabel().' / '.$assignment->department->label().' / '.$assignment->function->label(),
                'critical' => (bool) $assignment->critical,
            ])
            ->all();
    }

    /** @return array<int, array{department_id:int, section_id:int, function_id:int}> */
    public function functionPairFilterData(): array
    {
        return $this->functionAssignments
            ->map(static fn (CompetencyFunction $assignment): array => [
                'department_id' => (int) $assignment->department->factory_department_id,
                'section_id' => (int) $assignment->factory_section_id,
                'function_id' => (int) $assignment->factory_function_id,
            ])
            ->all();
    }

    /** @return array<int, int> */
    public function functionPairDepartmentIds(): array
    {
        return collect($this->functionPairFilterData())
            ->pluck('department_id')
            ->unique()
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    public function functionPairDepartmentLabels(): array
    {
        return $this->functionAssignments
            ->map(static fn (CompetencyFunction $assignment): string => $assignment->department->factoryDepartmentLabel())
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    public function functionPairSectionLabels(): array
    {
        return $this->functionAssignments
            ->map(static fn (CompetencyFunction $assignment): string => $assignment->department->factoryDepartmentLabel().' / '.$assignment->department->label())
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    public function functionPairFunctionLabels(): array
    {
        return $this->functionAssignments
            ->map(static fn (CompetencyFunction $assignment): string => $assignment->function->label())
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    public function functionPairKeys(): array
    {
        return $this->functionAssignments
            ->map(static fn (CompetencyFunction $assignment): string => $assignment->factory_section_id.':'.$assignment->factory_function_id)
            ->all();
    }

    /** @return array<string, bool> */
    public function functionPairCriticals(): array
    {
        return $this->functionAssignments
            ->mapWithKeys(static fn (CompetencyFunction $assignment): array => [
                $assignment->factory_section_id.':'.$assignment->factory_function_id => (bool) $assignment->critical,
            ])
            ->all();
    }

    public function hasCriticalFunction(): bool
    {
        return $this->functionAssignments->contains(static fn (CompetencyFunction $assignment): bool => (bool) $assignment->critical);
    }
}
