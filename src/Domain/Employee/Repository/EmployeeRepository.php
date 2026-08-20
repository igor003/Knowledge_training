<?php

namespace App\Domain\Employee\Repository;

use App\Domain\Employee\Model\Employee;
use App\Domain\Employee\Model\EmployeeAssignmentHistory;
use App\Domain\Employee\Model\EmployeePeriod;
use App\Domain\EmployeeStatus\Model\EmployeeStatus;
use App\Service\Eloquent\EloquentManager;
use Illuminate\Database\Eloquent\Collection;

final class EmployeeRepository
{
    public function __construct(private readonly EloquentManager $eloquent)
    {
    }

    /** @return Collection<int, Employee> */
    public function list(): Collection
    {
        $this->eloquent->boot();

        $employees = Employee::query()
            ->with([
                'department',
                'branch',
                'section',
                'position.functionType',
                'shift',
                'employeeStatus',
                'assignmentHistory.department',
                'assignmentHistory.section',
                'assignmentHistory.position',
                'assignmentHistory.shift',
                'periods',
            ])
            ->orderByDesc('status')
            ->orderBy('name')
            ->get();

        foreach ($employees as $employee) {
            $this->syncAutomaticStatus($employee);
        }

        return $employees;
    }

    public function findById(int $id): ?Employee
    {
        $this->eloquent->boot();

        $employee = Employee::query()->with('employeeStatus')->find($id);
        if ($employee !== null) {
            $this->syncAutomaticStatus($employee);
        }

        return $employee;
    }

    public function create(array $attributes, string $assignmentDate): Employee
    {
        $this->eloquent->boot();

        return Employee::query()->getConnection()->transaction(function () use ($attributes, $assignmentDate): Employee {
            $employee = Employee::query()->create($attributes);
            $this->createAssignment($employee, $attributes, $assignmentDate);
            $employee->load('employeeStatus');
            $this->syncDismissalPeriod($employee);

            return $employee;
        });
    }

    public function update(Employee $employee, array $attributes, string $assignmentDate): Employee
    {
        $this->eloquent->boot();

        return $employee->getConnection()->transaction(function () use ($employee, $attributes, $assignmentDate): Employee {
            $assignmentChanged = (int) $employee->factory_department_id !== (int) $attributes['factory_department_id']
                || (int) $employee->factory_section_id !== (int) $attributes['factory_section_id']
                || (int) $employee->factory_function_id !== (int) $attributes['factory_function_id']
                || (int) ($employee->work_shift_id ?? 0) !== (int) ($attributes['work_shift_id'] ?? 0);

            $employee->fill($attributes);
            $employee->save();

            if ($assignmentChanged) {
                EmployeeAssignmentHistory::query()
                    ->where('employee_id', $employee->id)
                    ->whereNull('date_to')
                    ->update(['date_to' => $assignmentDate]);
                $this->createAssignment($employee, $attributes, $assignmentDate);
            }

            $employee->load('employeeStatus');
            $this->syncDismissalPeriod($employee);

            return $employee;
        });
    }

    public function addPeriod(Employee $employee, string $type, string $dateFrom, ?string $dateTo): EmployeePeriod
    {
        $this->eloquent->boot();

        return $employee->getConnection()->transaction(function () use ($employee, $type, $dateFrom, $dateTo): EmployeePeriod {
            $period = EmployeePeriod::query()->create([
                'employee_id' => $employee->id,
                'period_type' => $type,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ]);

            if ($type === EmployeePeriod::TYPE_DISMISSAL && $dateFrom <= date('Y-m-d')) {
                $employee->dismissed_at = $dateFrom;
                $employee->save();
            }

            $this->syncAutomaticStatus($employee);

            return $period;
        });
    }

    /** @return Collection<int, EmployeeAssignmentHistory> */
    public function assignmentHistory(Employee $employee): Collection
    {
        $this->eloquent->boot();

        return EmployeeAssignmentHistory::query()
            ->where('employee_id', $employee->id)
            ->with(['department', 'section', 'position', 'shift'])
            ->orderByDesc('date_from')
            ->get();
    }

    /** @return Collection<int, EmployeePeriod> */
    public function periods(Employee $employee): Collection
    {
        $this->eloquent->boot();

        return EmployeePeriod::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('date_from')
            ->get();
    }

    public function findPeriod(Employee $employee, int $periodId): ?EmployeePeriod
    {
        $this->eloquent->boot();

        return EmployeePeriod::query()
            ->where('employee_id', $employee->id)
            ->whereKey($periodId)
            ->first();
    }

    public function updatePeriod(Employee $employee, EmployeePeriod $period, string $dateFrom, ?string $dateTo): EmployeePeriod
    {
        $this->eloquent->boot();

        return $employee->getConnection()->transaction(function () use ($employee, $period, $dateFrom, $dateTo): EmployeePeriod {
            $period->date_from = $dateFrom;
            $period->date_to = $dateTo;
            $period->save();
            $employee->load('employeeStatus');
            $this->syncAutomaticStatus($employee);

            return $period;
        });
    }

    public function deletePeriod(Employee $employee, EmployeePeriod $period): void
    {
        $this->eloquent->boot();

        $employee->getConnection()->transaction(function () use ($employee, $period): void {
            $period->delete();
            $employee->load('employeeStatus');
            $this->syncAutomaticStatus($employee);
        });
    }

    public function refreshAutomaticStatus(Employee $employee): void
    {
        $this->eloquent->boot();
        $employee->load('employeeStatus');
        $this->syncAutomaticStatus($employee);
    }

    private function createAssignment(Employee $employee, array $attributes, string $assignmentDate): void
    {
        EmployeeAssignmentHistory::query()->create([
            'employee_id' => $employee->id,
            'factory_department_id' => $attributes['factory_department_id'],
            'factory_section_id' => $attributes['factory_section_id'],
            'factory_function_id' => $attributes['factory_function_id'],
            'work_shift_id' => $attributes['work_shift_id'] ?: null,
            'date_from' => $assignmentDate,
        ]);
    }

    private function syncDismissalPeriod(Employee $employee): void
    {
        $period = EmployeePeriod::query()
            ->where('employee_id', $employee->id)
            ->where('period_type', EmployeePeriod::TYPE_DISMISSAL)
            ->where('active', true)
            ->whereNull('date_to')
            ->latest('date_from')
            ->first();

        if ($employee->employeeStatus?->isInactive() && $employee->dismissed_at !== null && $period === null) {
            EmployeePeriod::query()->create([
                'employee_id' => $employee->id,
                'period_type' => EmployeePeriod::TYPE_DISMISSAL,
                'date_from' => $employee->dismissed_at->format('Y-m-d'),
            ]);
        }

        if ($employee->employeeStatus?->isActive() && $period !== null) {
            $period->date_to = $employee->last_hired_at?->format('Y-m-d');
            $period->save();
        }
    }

    private function syncAutomaticStatus(Employee $employee): void
    {
        $today = date('Y-m-d');
        $currentStatus = $employee->employeeStatus;
        $dismissal = EmployeePeriod::query()
            ->where('employee_id', $employee->id)
            ->where('period_type', EmployeePeriod::TYPE_DISMISSAL)
            ->where('active', true)
            ->where('date_from', '<=', $today)
            ->where(static function ($query) use ($today): void {
                $query->whereNull('date_to')->orWhere('date_to', '>=', $today);
            })
            ->latest('date_from')
            ->first();

        $targetStatus = $dismissal !== null ? $this->findStatusByRole('inactive') : null;
        if ($targetStatus === null) {
            $vacation = EmployeePeriod::query()
                ->where('employee_id', $employee->id)
                ->where('period_type', EmployeePeriod::TYPE_VACATION)
                ->where('active', true)
                ->where('date_from', '<=', $today)
                ->where(static function ($query) use ($today): void {
                    $query->whereNull('date_to')->orWhere('date_to', '>=', $today);
                })
                ->latest('date_from')
                ->first();
            $targetStatus = $vacation !== null
                ? $this->findStatusByRole('vacation')
                : (($currentStatus?->isVacation() || $currentStatus?->isInactive())
                    ? $this->findStatusByRole('active')
                    : $currentStatus);
        }

        if ($targetStatus === null || $currentStatus?->id === $targetStatus->id) {
            return;
        }

        $employee->status = $targetStatus->code;
        $employee->employee_status_id = $targetStatus->id;
        $employee->save();
        $employee->setRelation('employeeStatus', $targetStatus);
    }

    private function findStatusByRole(string $role): ?EmployeeStatus
    {
        return EmployeeStatus::query()
            ->where('active', true)
            ->get()
            ->first(static function (EmployeeStatus $status) use ($role): bool {
                return match ($role) {
                    'vacation' => $status->isVacation(),
                    'inactive' => $status->isInactive(),
                    'active' => $status->isActive(),
                    default => false,
                };
            });
    }
}
