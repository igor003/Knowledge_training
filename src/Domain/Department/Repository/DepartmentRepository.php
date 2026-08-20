<?php

namespace App\Domain\Department\Repository;

use App\Domain\Department\Model\Department;
use App\Domain\Shared\Model\SimpleCatalogModel;
use App\Domain\Shared\Repository\SimpleCatalogRepository;
use App\Service\Eloquent\EloquentManager;
use Illuminate\Database\Eloquent\Collection;

final class DepartmentRepository extends SimpleCatalogRepository
{
    private EloquentManager $eloquent;

    public function __construct(EloquentManager $eloquent)
    {
        $this->eloquent = $eloquent;

        parent::__construct($eloquent, Department::class);
    }

    /**
     * @return Collection<int, Department>
     */
    public function list(): Collection
    {
        $this->eloquent->boot();

        return Department::query()
            ->leftJoin('factory_departments', 'factory_sections.factory_department_id', '=', 'factory_departments.id')
            ->select('factory_sections.*')
            ->withCount(['positions as functions_count'])
            ->with([
                'factoryDepartment',
                'positions' => static function ($query): void {
                    $query
                        ->orderBy('factory_section_function.sort_order')
                        ->orderBy('factory_functions.name')
                        ->orderBy('factory_functions.id');
                },
            ])
            ->orderByDesc('factory_sections.active')
            ->orderBy('factory_departments.sort_order')
            ->orderBy('factory_departments.name')
            ->orderBy('factory_sections.name')
            ->orderBy('factory_sections.id')
            ->get();
    }

    /**
     * @return Collection<int, Department>
     */
    public function listActive(): Collection
    {
        $this->eloquent->boot();

        return Department::query()
            ->leftJoin('factory_departments', 'factory_sections.factory_department_id', '=', 'factory_departments.id')
            ->select('factory_sections.*')
            ->with('factoryDepartment')
            ->where('factory_sections.active', true)
            ->orderBy('factory_departments.sort_order')
            ->orderBy('factory_departments.name')
            ->orderBy('factory_sections.name')
            ->orderBy('factory_sections.id')
            ->get();
    }

    /**
     * @param array<int, int|string> $positionIds
     */
    public function syncPositions(SimpleCatalogModel $department, array $positionIds): void
    {
        $this->eloquent->boot();

        if (!$department instanceof Department) {
            return;
        }

        $sync = [];
        $sortOrder = 10;
        foreach ($positionIds as $positionId) {
            $id = (int) $positionId;
            if ($id > 0 && !isset($sync[$id])) {
                $sync[$id] = ['sort_order' => $sortOrder];
                $sortOrder += 10;
            }
        }

        $department->positions()->sync($sync);
        $department->load(['positions' => static function ($query): void {
            $query
                ->orderBy('factory_section_function.sort_order')
                ->orderBy('factory_functions.name')
                ->orderBy('factory_functions.id');
        }]);
    }

    public function physicallyDelete(SimpleCatalogModel $department): void
    {
        $this->eloquent->boot();

        if (!$department instanceof Department) {
            return;
        }

        $department->getConnection()->transaction(function () use ($department): void {
            $this->deleteRelations($department);
            $department->delete();
        });
    }

    private function deleteRelations(Department $department): void
    {
        $department->positions()->detach();
    }
}
