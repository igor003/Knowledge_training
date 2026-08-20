<?php

namespace App\Domain\Shift\Repository;

use App\Domain\Shared\Repository\SimpleCatalogRepository;
use App\Domain\Employee\Model\Employee;
use App\Domain\Employee\Model\EmployeeAssignmentHistory;
use App\Domain\Shift\Model\WorkShift;
use App\Service\Eloquent\EloquentManager;

final class WorkShiftRepository extends SimpleCatalogRepository
{
    public function __construct(EloquentManager $eloquent)
    {
        parent::__construct($eloquent, WorkShift::class);
    }

    public function physicallyDelete(WorkShift $shift): void
    {
        $shift->getConnection()->transaction(function () use ($shift): void {
            Employee::query()
                ->where('work_shift_id', (int) $shift->id)
                ->update(['work_shift_id' => null]);
            EmployeeAssignmentHistory::query()
                ->where('work_shift_id', (int) $shift->id)
                ->update(['work_shift_id' => null]);
            $shift->delete();
        });
    }
}
