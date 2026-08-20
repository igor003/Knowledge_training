<?php

namespace App\Domain\FactoryDepartment\Repository;

use App\Domain\Department\Model\Department;
use App\Domain\FactoryDepartment\Model\FactoryDepartment;
use App\Domain\Shared\Model\SimpleCatalogModel;
use App\Domain\Shared\Repository\SimpleCatalogRepository;
use App\Service\Eloquent\EloquentManager;
use Illuminate\Database\Eloquent\Collection;

final class FactoryDepartmentRepository extends SimpleCatalogRepository
{
    private EloquentManager $eloquent;

    public function __construct(EloquentManager $eloquent)
    {
        $this->eloquent = $eloquent;

        parent::__construct($eloquent, FactoryDepartment::class);
    }

    /**
     * @return Collection<int, FactoryDepartment>
     */
    public function list(): Collection
    {
        $this->eloquent->boot();

        return FactoryDepartment::query()
            ->with(['sections', 'trainingCourses'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, FactoryDepartment>
     */
    public function listActive(): Collection
    {
        $this->eloquent->boot();

        return FactoryDepartment::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function create(string $code, string $name, array $attributes = []): SimpleCatalogModel
    {
        $attributes['sort_order'] ??= $this->nextSortOrder();

        return parent::create($code, $name, $attributes);
    }

    /**
     * @param array<int, int|string> $orderedIds
     */
    public function reorder(array $orderedIds): void
    {
        $this->eloquent->boot();

        $ids = [];
        foreach ($orderedIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        if ($ids === []) {
            return;
        }

        (new FactoryDepartment())->getConnection()->transaction(function () use ($ids): void {
            $departments = $this->list();
            $ordered = [];

            foreach ($ids as $id) {
                $department = $departments->firstWhere('id', $id);
                if ($department instanceof FactoryDepartment) {
                    $ordered[$id] = $department;
                }
            }

            foreach ($departments as $department) {
                $id = (int) $department->id;
                if (!isset($ordered[$id])) {
                    $ordered[$id] = $department;
                }
            }

            $sortOrder = 10;
            foreach ($ordered as $department) {
                $department->setAttribute('sort_order', $sortOrder);
                $department->save();
                $sortOrder += 10;
            }
        });
    }

    /**
     * @return array<int, array{id:int, name:string, sort_order:int, active:bool}>
     */
    public function orderSnapshot(): array
    {
        return $this->list()
            ->map(static fn (FactoryDepartment $department): array => [
                'id' => (int) $department->id,
                'name' => $department->label(),
                'sort_order' => (int) $department->getAttribute('sort_order'),
                'active' => (bool) $department->active,
            ])
            ->all();
    }

    public function physicallyDelete(FactoryDepartment $department): void
    {
        $this->eloquent->boot();

        $department->getConnection()->transaction(function () use ($department): void {
            $department->trainingCourses()->detach();
            $department->getConnection()
                ->table('factory_department_training_course_monthly_plans')
                ->where('factory_department_id', (int) $department->id)
                ->delete();

            $department->sections()->with('positions')->get()->each(function (Department $section): void {
                $section->positions()->detach();
                $section->delete();
            });

            $department->delete();
        });
    }

    private function nextSortOrder(): int
    {
        $this->eloquent->boot();

        return ((int) FactoryDepartment::query()->max('sort_order')) + 10;
    }
}
