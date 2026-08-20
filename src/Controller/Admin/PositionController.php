<?php

namespace App\Controller\Admin;

use App\Domain\Access\Repository\PermissionRepository;
use App\Domain\Access\Model\RolePermission;
use App\Domain\Competency\Model\Competency;
use App\Domain\Competency\Repository\CompetencyRepository;
use App\Domain\Employee\Model\Employee;
use App\Domain\Employee\Model\EmployeeAssignmentHistory;
use App\Domain\FunctionType\Repository\FunctionTypeRepository;
use App\Domain\FactoryDepartment\Repository\FactoryDepartmentRepository;
use App\Domain\Department\Repository\DepartmentRepository;
use App\Domain\Position\Repository\PositionRepository;
use App\Domain\Shared\Model\SimpleCatalogModel;
use App\Domain\Shared\Repository\SimpleCatalogRepository;
use App\Service\Audit\AuditLogger;
use App\Service\Auth\AuthSession;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PositionController extends SimpleCatalogController
{
    private const TRANSLATION_PREFIX = 'factory_functions';
    private const ROUTE_PREFIX = 'admin_factory_functions';

    public function __construct(
        private readonly FunctionTypeRepository $functionTypes,
        private readonly DepartmentRepository $departments,
        private readonly FactoryDepartmentRepository $factoryDepartments,
        private readonly CompetencyRepository $competencies,
    ) {
    }

    #[Route('/admin/factory-functions', name: 'admin_factory_functions_index', methods: ['GET'])]
    public function index(AuthSession $auth, PositionRepository $positions): Response
    {
        return $this->renderIndex($auth, $positions, PermissionRepository::ENTITY_FACTORY_FUNCTIONS, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    #[Route('/admin/factory-functions/create', name: 'admin_factory_functions_create', methods: ['POST'])]
    public function create(Request $request, AuthSession $auth, PositionRepository $positions, AuditLogger $audit): Response
    {
        return $this->createRecord($request, $auth, $positions, $audit, PermissionRepository::ENTITY_FACTORY_FUNCTIONS, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    #[Route('/admin/factory-functions/{id}/update', name: 'admin_factory_functions_update', methods: ['POST'])]
    public function update(int $id, Request $request, AuthSession $auth, PositionRepository $positions, AuditLogger $audit): Response
    {
        return $this->updateRecord($id, $request, $auth, $positions, $audit, PermissionRepository::ENTITY_FACTORY_FUNCTIONS, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    #[Route('/admin/factory-functions/{id}/toggle-status', name: 'admin_factory_functions_toggle_status', methods: ['POST'])]
    public function toggleStatus(int $id, Request $request, AuthSession $auth, PositionRepository $positions, AuditLogger $audit): Response
    {
        return $this->toggleRecordStatus($id, $request, $auth, $positions, $audit, PermissionRepository::ENTITY_FACTORY_FUNCTIONS, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    #[Route('/admin/factory-functions/{id}/delete', name: 'admin_factory_functions_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, AuthSession $auth, PositionRepository $positions, AuditLogger $audit): Response
    {
        $this->denyPermission($auth, PermissionRepository::ENTITY_FACTORY_FUNCTIONS, RolePermission::ACTION_DELETE);

        $function = $positions->findById($id);
        if ($function === null) {
            return $this->redirectToRoute(self::ROUTE_PREFIX.'_index');
        }

        if (Employee::query()->where('factory_function_id', $id)->exists()
            || EmployeeAssignmentHistory::query()->where('factory_function_id', $id)->exists()
            || Competency::query()->where('factory_function_id', $id)->exists()) {
            $this->addFlash('danger', 'error.function_in_use');

            return $this->redirectToRoute(self::ROUTE_PREFIX.'_index');
        }

        $before = $this->catalogSnapshot($function);
        $audit->log($request, $auth, RolePermission::ACTION_DELETE, PermissionRepository::ENTITY_FACTORY_FUNCTIONS, $function->id, $function->label(), $before, null, ['physical_delete' => true]);
        $positions->physicallyDelete($function);
        $this->addFlash('success', 'success.deleted');

        return $this->redirectToRoute(self::ROUTE_PREFIX.'_index');
    }

    /**
     * @return array<string, mixed>
     */
    protected function catalogTemplateVariables(): array
    {
        return [
            'show_alias' => true,
            'show_function_type' => true,
            'show_departments' => true,
            'show_column_filters' => true,
            'show_relation_department_filter' => true,
            'show_department_count' => true,
            'show_competencies' => true,
            'can_physical_delete' => true,
            'physical_delete_confirmation' => 'factory_functions.confirm_physical_delete',
            'departments' => $this->departments->listActive(),
            'factory_departments' => $this->factoryDepartments->listActive(),
            'competencies' => $this->competencies->listActive(),
            'function_types' => $this->functionTypes->listActive(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraCatalogForm(Request $request): array
    {
        return [
            'alias' => trim((string) $request->request->get('alias', '')),
            'function_type_id' => (int) $request->request->get('function_type_id', 0) ?: null,
            'department_ids' => $this->activeDepartmentIdsFromRequest((array) $request->request->all('department_ids')),
            'competency_assignments' => $this->competencyAssignmentsFromRequest((array) $request->request->all('competency_assignments')),
        ];
    }

    /**
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    protected function extraCatalogAttributes(array $form): array
    {
        $alias = trim((string) ($form['alias'] ?? ''));

        return [
            'alias' => $alias !== '' ? $alias : null,
            'factory_function_type_id' => (int) ($form['function_type_id'] ?? 0) ?: null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraCatalogSnapshot(SimpleCatalogModel $record): array
    {
        if (method_exists($record, 'loadMissing')) {
            $record->loadMissing(['departments', 'competencyAssignments']);
        }

        return [
            'alias' => $record->getAttribute('alias'),
            'function_type_id' => $record->getAttribute('factory_function_type_id'),
            'function_type' => method_exists($record, 'functionTypeLabel') ? $record->functionTypeLabel() : null,
            'department_ids' => method_exists($record, 'departmentIds') ? $record->departmentIds() : [],
            'departments' => method_exists($record, 'departmentLabels') ? $record->departmentLabels() : [],
            'competency_assignments' => method_exists($record, 'competencyAssignmentData') ? $record->competencyAssignmentData() : [],
        ];
    }

    /**
     * @param array<string, mixed> $form
     */
    protected function afterCreateRecord(SimpleCatalogModel $record, array $form, SimpleCatalogRepository $repository): void
    {
        if ($repository instanceof PositionRepository) {
            $repository->syncDepartments($record, (array) ($form['department_ids'] ?? []));
            $this->competencies->syncFunctionAssignments((int) $record->id, $this->allowedCompetencyAssignments($form['competency_assignments'] ?? [], $form['department_ids'] ?? []));
        }
    }

    /**
     * @param array<string, mixed> $form
     */
    protected function afterUpdateRecord(SimpleCatalogModel $record, array $form, SimpleCatalogRepository $repository): void
    {
        $this->afterCreateRecord($record, $form, $repository);
    }

    /**
     * @param array<int, mixed> $departmentIds
     * @return array<int, int>
     */
    private function activeDepartmentIdsFromRequest(array $departmentIds): array
    {
        $activeDepartmentIds = [];
        foreach ($this->departments->listActive() as $department) {
            $activeDepartmentIds[(int) $department->id] = true;
        }

        $allowedIds = [];
        foreach ($departmentIds as $departmentId) {
            $id = (int) $departmentId;
            if ($id > 0 && isset($activeDepartmentIds[$id])) {
                $allowedIds[$id] = $id;
            }
        }

        return array_values($allowedIds);
    }

    /** @param array<int, mixed> $rawAssignments */
    private function competencyAssignmentsFromRequest(array $rawAssignments): array
    {
        $assignments = [];
        foreach ($rawAssignments as $rawAssignment) {
            [$departmentId, $competencyId, $critical] = array_pad(explode(':', (string) $rawAssignment, 3), 3, '0');
            $departmentId = (int) $departmentId;
            $competencyId = (int) $competencyId;
            if ($departmentId > 0 && $competencyId > 0) {
                $key = $departmentId.':'.$competencyId;
                $assignments[$key] = [
                    'factory_section_id' => $departmentId,
                    'competency_id' => $competencyId,
                    'critical' => (bool) ((int) $critical),
                ];
            }
        }

        return array_values($assignments);
    }

    /** @param array<int, array<string, mixed>> $assignments @param array<int, mixed> $departmentIds */
    private function allowedCompetencyAssignments(array $assignments, array $departmentIds): array
    {
        $allowedDepartments = array_fill_keys(array_map('intval', $departmentIds), true);
        $activeCompetencies = array_fill_keys($this->competencies->listActive()->pluck('id')->map(static fn ($id): int => (int) $id)->all(), true);

        return array_values(array_filter($assignments, static fn (array $assignment): bool => isset($allowedDepartments[$assignment['factory_section_id']]) && isset($activeCompetencies[$assignment['competency_id']])));
    }
}
