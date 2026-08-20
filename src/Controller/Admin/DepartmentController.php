<?php

namespace App\Controller\Admin;

use App\Domain\Access\Model\RolePermission;
use App\Domain\Access\Repository\PermissionRepository;
use App\Domain\Department\Repository\DepartmentRepository;
use App\Domain\FactoryDepartment\Repository\FactoryDepartmentRepository;
use App\Domain\Position\Repository\PositionRepository;
use App\Domain\Shared\Model\SimpleCatalogModel;
use App\Domain\Shared\Repository\SimpleCatalogRepository;
use App\Service\Audit\AuditLogger;
use App\Service\Auth\AuthSession;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DepartmentController extends SimpleCatalogController
{
    private const TRANSLATION_PREFIX = 'factory_sections';
    private const ROUTE_PREFIX = 'admin_factory_sections';

    public function __construct(
        private readonly FactoryDepartmentRepository $factoryDepartments,
        private readonly PositionRepository $positions,
    ) {
    }

    #[Route('/admin/factory-sections', name: 'admin_factory_sections_index', methods: ['GET'])]
    public function index(AuthSession $auth, DepartmentRepository $departments): Response
    {
        return $this->renderIndex($auth, $departments, PermissionRepository::ENTITY_FACTORY_SECTIONS, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    #[Route('/admin/factory-sections/create', name: 'admin_factory_sections_create', methods: ['POST'])]
    public function create(Request $request, AuthSession $auth, DepartmentRepository $departments, AuditLogger $audit): Response
    {
        return $this->createRecord($request, $auth, $departments, $audit, PermissionRepository::ENTITY_FACTORY_SECTIONS, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    #[Route('/admin/factory-sections/{id}/update', name: 'admin_factory_sections_update', methods: ['POST'])]
    public function update(int $id, Request $request, AuthSession $auth, DepartmentRepository $departments, AuditLogger $audit): Response
    {
        return $this->updateRecord($id, $request, $auth, $departments, $audit, PermissionRepository::ENTITY_FACTORY_SECTIONS, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    #[Route('/admin/factory-sections/{id}/toggle-status', name: 'admin_factory_sections_toggle_status', methods: ['POST'])]
    public function toggleStatus(int $id, Request $request, AuthSession $auth, DepartmentRepository $departments, AuditLogger $audit): Response
    {
        return $this->toggleRecordStatus($id, $request, $auth, $departments, $audit, PermissionRepository::ENTITY_FACTORY_SECTIONS, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    #[Route('/admin/factory-sections/{id}/delete', name: 'admin_factory_sections_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, AuthSession $auth, DepartmentRepository $departments, AuditLogger $audit): Response
    {
        $this->denyPermission($auth, PermissionRepository::ENTITY_FACTORY_SECTIONS, RolePermission::ACTION_DELETE);

        $department = $departments->findById($id);
        if ($department === null) {
            return $this->redirectToRoute(self::ROUTE_PREFIX . '_index');
        }

        $before = $this->catalogSnapshot($department);
        $audit->log(
            $request,
            $auth,
            RolePermission::ACTION_DELETE,
            PermissionRepository::ENTITY_FACTORY_SECTIONS,
            $department->id,
            $department->label(),
            $before,
            null,
            ['physical_delete' => true],
        );

        $departments->physicallyDelete($department);
        $this->addFlash('success', 'success.deleted');

        return $this->redirectToRoute(self::ROUTE_PREFIX . '_index');
    }

    /**
     * @return array<string, mixed>
     */
    protected function catalogTemplateVariables(): array
    {
        return [
            'show_factory_department' => true,
            'show_column_filters' => true,
            'show_process_code' => true,
            'show_positions' => true,
            'show_function_count' => true,
            'can_physical_delete' => true,
            'factory_departments' => $this->factoryDepartments->listActive(),
            'positions' => $this->positions->listActive(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraCatalogForm(Request $request): array
    {
        $processCode = strtoupper(trim((string) $request->request->get('process_code', '')));

        return [
            'factory_department_id' => $this->activeFactoryDepartmentIdFromRequest((int) $request->request->get('factory_department_id', 0)),
            'process_code' => $processCode,
            'position_ids' => $this->activePositionIdsFromRequest((array) $request->request->all('position_ids')),
        ];
    }

    /**
     * @param array<string, mixed> $form
     */
    protected function validateExtraCatalogForm(array $form): ?string
    {
        $processCode = (string) ($form['process_code'] ?? '');

        if ((int) ($form['factory_department_id'] ?? 0) <= 0) {
            return 'error.required_department_factory_department';
        }

        if ($processCode !== '' && !preg_match('/^[A-Z0-9]{2}$/', $processCode)) {
            return 'error.invalid_process_code';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    protected function extraCatalogAttributes(array $form): array
    {
        $processCode = (string) ($form['process_code'] ?? '');

        return [
            'factory_department_id' => (int) ($form['factory_department_id'] ?? 0),
            'process_code' => $processCode !== '' ? $processCode : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraCatalogSnapshot(SimpleCatalogModel $record): array
    {
        if (method_exists($record, 'loadMissing')) {
            $record->loadMissing(['factoryDepartment', 'positions']);
        }

        return [
            'factory_department_id' => $record->getAttribute('factory_department_id'),
            'factory_department' => method_exists($record, 'factoryDepartmentLabel') ? $record->factoryDepartmentLabel() : null,
            'process_code' => $record->getAttribute('process_code'),
            'position_ids' => method_exists($record, 'positionIds') ? $record->positionIds() : [],
            'positions' => method_exists($record, 'positionLabels') ? $record->positionLabels() : [],
        ];
    }

    /** @param array<string, mixed> $form */
    protected function afterCreateRecord(SimpleCatalogModel $record, array $form, SimpleCatalogRepository $repository): void
    {
        if ($repository instanceof DepartmentRepository) {
            $repository->syncPositions($record, (array) ($form['position_ids'] ?? []));
        }
    }

    /** @param array<string, mixed> $form */
    protected function afterUpdateRecord(SimpleCatalogModel $record, array $form, SimpleCatalogRepository $repository): void
    {
        $this->afterCreateRecord($record, $form, $repository);
    }

    private function activeFactoryDepartmentIdFromRequest(int $factoryDepartmentId): ?int
    {
        if ($factoryDepartmentId <= 0) {
            return null;
        }

        foreach ($this->factoryDepartments->listActive() as $department) {
            if ((int) $department->id === $factoryDepartmentId) {
                return $factoryDepartmentId;
            }
        }

        return null;
    }

    /** @param array<int, mixed> $positionIds */
    private function activePositionIdsFromRequest(array $positionIds): array
    {
        $activePositionIds = [];
        foreach ($this->positions->listActive() as $position) {
            $activePositionIds[(int) $position->id] = true;
        }

        $allowedIds = [];
        foreach ($positionIds as $positionId) {
            $id = (int) $positionId;
            if ($id > 0 && isset($activePositionIds[$id])) {
                $allowedIds[$id] = $id;
            }
        }

        return array_values($allowedIds);
    }

}
