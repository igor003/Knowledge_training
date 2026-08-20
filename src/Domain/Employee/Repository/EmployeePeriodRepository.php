<?php

namespace App\Domain\Employee\Repository;

use App\Domain\Employee\Model\Employee;
use App\Domain\Employee\Model\EmployeePeriod;
use App\Service\Eloquent\EloquentManager;
use Illuminate\Database\Eloquent\Collection;

final class EmployeePeriodRepository
{
    public function __construct(private readonly EloquentManager $eloquent)
    {
    }

    /** @return Collection<int, EmployeePeriod> */
    public function list(): Collection
    {
        $this->eloquent->boot();

        return EmployeePeriod::query()
            ->with('employee')
            ->orderByDesc('date_from')
            ->orderByDesc('id')
            ->get();
    }

    public function findById(int $id): ?EmployeePeriod
    {
        $this->eloquent->boot();

        return EmployeePeriod::query()->with('employee')->find($id);
    }

    public function create(array $attributes): EmployeePeriod
    {
        $this->eloquent->boot();

        return EmployeePeriod::query()->create($attributes);
    }

    public function update(EmployeePeriod $period, string $dateFrom, ?string $dateTo, ?string $note): EmployeePeriod
    {
        $period->date_from = $dateFrom;
        $period->date_to = $dateTo;
        $period->note = $note;
        $period->save();

        return $period;
    }

    public function delete(EmployeePeriod $period): void
    {
        $period->delete();
    }

    public function deactivate(EmployeePeriod $period): EmployeePeriod
    {
        $period->active = false;
        $period->save();

        return $period;
    }

    public function hasOverlappingVacation(int $employeeId, string $dateFrom, ?string $dateTo, ?int $exceptId = null): bool
    {
        $query = EmployeePeriod::query()
            ->where('employee_id', $employeeId)
            ->where('period_type', EmployeePeriod::TYPE_VACATION)
            ->where('active', true)
            ->where('date_from', '<=', $dateTo ?? '9999-12-31')
            ->where(static function ($query) use ($dateFrom): void {
                $query->whereNull('date_to')->orWhere('date_to', '>=', $dateFrom);
            });

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }
}
