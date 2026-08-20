<?php

namespace App\Domain\Employee\Model;

use App\Domain\Department\Model\Department;
use App\Domain\FactoryDepartment\Model\FactoryDepartment;
use App\Domain\FactoryBranch\Model\FactoryBranch;
use App\Domain\EmployeeStatus\Model\EmployeeStatus;
use App\Domain\Position\Model\Position;
use App\Domain\Shift\Model\WorkShift;
use App\Domain\Shared\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Employee extends BaseModel
{
    protected $table = 'employees';

    protected $casts = [
        'factory_department_id' => 'integer',
        'factory_branch_id' => 'integer',
        'factory_section_id' => 'integer',
        'factory_function_id' => 'integer',
        'work_shift_id' => 'integer',
        'employee_status_id' => 'integer',
        'formator' => 'boolean',
        'last_hired_at' => 'date',
        'dismissed_at' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(FactoryDepartment::class, 'factory_department_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(FactoryBranch::class, 'factory_branch_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'factory_section_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'factory_function_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class, 'work_shift_id');
    }

    public function employeeStatus(): BelongsTo
    {
        return $this->belongsTo(EmployeeStatus::class, 'employee_status_id');
    }

    public function employeeStatusLabel(): string
    {
        return $this->getRelationValue('employeeStatus')?->label() ?? '';
    }

    public function branchLabel(): string
    {
        return $this->getRelationValue('branch')?->label() ?? '-';
    }

    public function departmentColor(): string
    {
        return (string) ($this->getRelationValue('department')?->getAttribute('color') ?: '#C8E8D2');
    }

    public function assignmentHistory(): HasMany
    {
        return $this->hasMany(EmployeeAssignmentHistory::class)->orderByDesc('date_from');
    }

    public function currentAssignmentDateLabel(): string
    {
        $assignment = $this->relationLoaded('assignmentHistory')
            ? $this->assignmentHistory->first(static fn (EmployeeAssignmentHistory $item): bool => $item->getAttribute('date_to') === null)
            : $this->assignmentHistory()->whereNull('date_to')->latest('date_from')->first();

        return $assignment?->getAttribute('date_from')?->format('d-m-Y') ?? '-';
    }

    public function periods(): HasMany
    {
        return $this->hasMany(EmployeePeriod::class)->orderByDesc('date_from');
    }

    public function label(): string
    {
        return (string) $this->name;
    }
}
