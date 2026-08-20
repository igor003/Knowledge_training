<?php

namespace App\Domain\EmployeeStatus\Repository;

use App\Domain\EmployeeStatus\Model\EmployeeStatus;
use App\Domain\Employee\Model\Employee;
use App\Domain\Shared\Repository\SimpleCatalogRepository;
use App\Service\Eloquent\EloquentManager;

final class EmployeeStatusRepository extends SimpleCatalogRepository
{
    private readonly EloquentManager $eloquent;

    public function __construct(EloquentManager $eloquent)
    {
        $this->eloquent = $eloquent;
        parent::__construct($eloquent, EmployeeStatus::class);
    }

    public function nameExistsExcept(string $name, ?int $exceptId = null): bool
    {
        $query = EmployeeStatus::query()->where('name', trim($name));

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function physicallyDelete(EmployeeStatus $status): void
    {
        $this->eloquent->boot();

        $status->getConnection()->transaction(function () use ($status): void {
            Employee::query()
                ->where('employee_status_id', (int) $status->id)
                ->update(['employee_status_id' => null]);
            $status->delete();
        });
    }
}
