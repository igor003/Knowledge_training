<?php

namespace App\Domain\EmployeeStatus\Model;

use App\Domain\Employee\Model\Employee;
use App\Domain\Shared\Model\SimpleCatalogModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EmployeeStatus extends SimpleCatalogModel
{
    protected $table = 'employee_statuses';

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'employee_status_id');
    }

    public function isVacation(): bool
    {
        return $this->matches(['vacances', 'vacation', 'concediu', 'ferie', 'conge', 'отпуск']);
    }

    public function isInactive(): bool
    {
        return $this->matches(['inactive', 'inactiv', 'неактивен']);
    }

    public function isActive(): bool
    {
        return $this->matches(['active', 'activ', 'активен']);
    }

    private function matches(array $values): bool
    {
        $code = strtolower(trim((string) $this->code));
        $name = mb_strtolower(trim((string) $this->name));
        $values = array_map(static fn (string $value): string => mb_strtolower($value), $values);

        return in_array($code, $values, true) || in_array($name, $values, true);
    }
}
