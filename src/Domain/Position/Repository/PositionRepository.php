<?php

namespace App\Domain\Position\Repository;

use App\Domain\Position\Model\Position;
use App\Domain\Shared\Model\SimpleCatalogModel;
use App\Domain\Shared\Repository\SimpleCatalogRepository;
use App\Service\Eloquent\EloquentManager;
use Illuminate\Database\Eloquent\Collection;

final class PositionRepository extends SimpleCatalogRepository
{
    private EloquentManager $eloquent;

    public function __construct(EloquentManager $eloquent)
    {
        $this->eloquent = $eloquent;

        parent::__construct($eloquent, Position::class);
    }

    /**
     * @return Collection<int, Position>
     */
    public function list(): Collection
    {
        $this->eloquent->boot();

        return Position::query()
            ->with(['functionType', 'departments' => static function ($query): void {
                $query
                    ->leftJoin('factory_departments', 'factory_sections.factory_department_id', '=', 'factory_departments.id')
                    ->select('factory_sections.*')
                    ->orderBy('factory_departments.sort_order')
                    ->orderBy('factory_departments.name')
                    ->orderBy('factory_sections.name')
                    ->orderBy('factory_sections.id');
            }, 'competencyAssignments'])
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Position>
     */
    public function listActive(): Collection
    {
        $this->eloquent->boot();

        return Position::query()
            ->with(['departments' => static function ($query): void {
                $query
                    ->leftJoin('factory_departments', 'factory_sections.factory_department_id', '=', 'factory_departments.id')
                    ->select('factory_sections.*')
                    ->orderBy('factory_departments.sort_order')
                    ->orderBy('factory_departments.name')
                    ->orderBy('factory_sections.name')
                    ->orderBy('factory_sections.id');
            }])
            ->where('active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param array<int, int|string> $departmentIds
     */
    public function syncDepartments(SimpleCatalogModel $position, array $departmentIds): void
    {
        $this->eloquent->boot();

        if (!$position instanceof Position) {
            return;
        }

        $ids = [];
        foreach ($departmentIds as $departmentId) {
            $id = (int) $departmentId;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        $ids = array_values($ids);
        $sync = [];
        $connection = $position->getConnection();
        $existingSortOrders = $connection
            ->table('factory_section_function')
            ->where('factory_function_id', (int) $position->id)
            ->whereIn('factory_section_id', $ids)
            ->pluck('sort_order', 'factory_section_id')
            ->all();

        foreach ($ids as $id) {
            $sync[$id] = [
                'sort_order' => (int) ($existingSortOrders[$id] ?? $this->nextSectionFunctionSortOrder($id)),
            ];
        }

        $position->departments()->sync($sync);
        $position->load(['departments' => static function ($query): void {
            $query
                ->leftJoin('factory_departments', 'factory_sections.factory_department_id', '=', 'factory_departments.id')
                ->select('factory_sections.*')
                ->orderBy('factory_departments.sort_order')
                ->orderBy('factory_departments.name')
                ->orderBy('factory_sections.name')
                ->orderBy('factory_sections.id');
        }]);
    }

    public function physicallyDelete(SimpleCatalogModel $position): void
    {
        $this->eloquent->boot();

        if (!$position instanceof Position) {
            return;
        }

        $position->getConnection()->transaction(function () use ($position): void {
            $position->departments()->detach();
            $position->delete();
        });
    }

    private function nextSectionFunctionSortOrder(int $departmentId): int
    {
        return ((int) (new Position())->getConnection()
            ->table('factory_section_function')
            ->where('factory_section_id', $departmentId)
            ->max('sort_order')) + 10;
    }
}
