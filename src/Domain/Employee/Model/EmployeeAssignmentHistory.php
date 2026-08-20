<?php

namespace App\Domain\Employee\Model;

use App\Domain\Department\Model\Department;
use App\Domain\FactoryDepartment\Model\FactoryDepartment;
use App\Domain\Position\Model\Position;
use App\Domain\Shift\Model\WorkShift;
use App\Domain\Shared\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EmployeeAssignmentHistory extends BaseModel
{
    protected $table = 'employee_assignment_history';

    protected $casts = [
        'employee_id' => 'integer',
        'factory_department_id' => 'integer',
        'factory_section_id' => 'integer',
        'factory_function_id' => 'integer',
        'work_shift_id' => 'integer',
        'date_from' => 'date',
        'date_to' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(FactoryDepartment::class, 'factory_department_id');
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
}
