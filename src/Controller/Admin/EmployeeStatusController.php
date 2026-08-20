<?php

namespace App\Controller\Admin;

use App\Domain\Access\Model\RolePermission;
use App\Domain\Access\Repository\PermissionRepository;
use App\Domain\EmployeeStatus\Repository\EmployeeStatusRepository;
use App\Service\Audit\AuditLogger;
use App\Service\Auth\AuthSession;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;

final class EmployeeStatusController extends SimpleCatalogController
{
    private const TRANSLATION_PREFIX = 'employee_statuses';
    private const ROUTE_PREFIX = 'admin_employee_statuses';

    public function __construct(
        private readonly EmployeeStatusRepository $employeeStatuses,
        private readonly RequestStack $requestStack,
    )
    {
    }

    #[Route('/admin/employee-statuses', name: 'admin_employee_statuses_index', methods: ['GET'])]
    public function index(AuthSession $auth, EmployeeStatusRepository $statuses): Response
    {
        return $this->renderIndex($auth, $statuses, PermissionRepository::ENTITY_EMPLOYEE_STATUSES, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    #[Route('/admin/employee-statuses/create', name: 'admin_employee_statuses_create', methods: ['POST'])]
    public function create(Request $request, AuthSession $auth, EmployeeStatusRepository $statuses, AuditLogger $audit): Response
    {
        return $this->createRecord($request, $auth, $statuses, $audit, PermissionRepository::ENTITY_EMPLOYEE_STATUSES, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    #[Route('/admin/employee-statuses/{id}/update', name: 'admin_employee_statuses_update', methods: ['POST'])]
    public function update(int $id, Request $request, AuthSession $auth, EmployeeStatusRepository $statuses, AuditLogger $audit): Response
    {
        return $this->updateRecord($id, $request, $auth, $statuses, $audit, PermissionRepository::ENTITY_EMPLOYEE_STATUSES, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    #[Route('/admin/employee-statuses/{id}/toggle-status', name: 'admin_employee_statuses_toggle_status', methods: ['POST'])]
    public function toggleStatus(int $id, Request $request, AuthSession $auth, EmployeeStatusRepository $statuses, AuditLogger $audit): Response
    {
        return $this->toggleRecordStatus($id, $request, $auth, $statuses, $audit, PermissionRepository::ENTITY_EMPLOYEE_STATUSES, self::TRANSLATION_PREFIX, self::ROUTE_PREFIX);
    }

    #[Route('/admin/employee-statuses/{id}/delete', name: 'admin_employee_statuses_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, AuthSession $auth, EmployeeStatusRepository $statuses, AuditLogger $audit): Response
    {
        $this->denyPermission($auth, PermissionRepository::ENTITY_EMPLOYEE_STATUSES, RolePermission::ACTION_DELETE);

        $status = $statuses->findById($id);
        if ($status === null) {
            return $this->redirectToRoute(self::ROUTE_PREFIX . '_index');
        }

        $before = $this->catalogSnapshot($status);
        $audit->log($request, $auth, RolePermission::ACTION_DELETE, PermissionRepository::ENTITY_EMPLOYEE_STATUSES, $status->id, $status->label(), $before, null, ['physical_delete' => true]);
        $statuses->physicallyDelete($status);
        $this->addFlash('success', 'success.deleted');

        return $this->redirectToRoute(self::ROUTE_PREFIX . '_index');
    }

    protected function catalogTemplateVariables(): array
    {
        return [
            'can_physical_delete' => true,
            'physical_delete_confirmation' => 'employee_statuses.confirm_physical_delete',
        ];
    }

    protected function validateExtraCatalogForm(array $form): ?string
    {
        $id = (int) ($this->requestStack->getCurrentRequest()?->attributes->get('id') ?? 0) ?: null;

        return $this->employeeStatuses->nameExistsExcept((string) ($form['name'] ?? ''), $id)
            ? 'error.duplicate_employee_status_name'
            : null;
    }

}
