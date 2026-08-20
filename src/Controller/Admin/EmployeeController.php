<?php

namespace App\Controller\Admin;

use App\Domain\Access\Model\RolePermission;
use App\Domain\Access\Repository\PermissionRepository;
use App\Domain\Department\Model\Department;
use App\Domain\Department\Repository\DepartmentRepository;
use App\Domain\Employee\Model\Employee;
use App\Domain\Employee\Model\EmployeePeriod;
use App\Domain\Employee\Repository\EmployeeRepository;
use App\Domain\EmployeeStatus\Model\EmployeeStatus;
use App\Domain\EmployeeStatus\Repository\EmployeeStatusRepository;
use App\Domain\FactoryDepartment\Model\FactoryDepartment;
use App\Domain\FactoryDepartment\Repository\FactoryDepartmentRepository;
use App\Domain\FactoryBranch\Model\FactoryBranch;
use App\Domain\FactoryBranch\Repository\FactoryBranchRepository;
use App\Domain\Position\Model\Position;
use App\Domain\Position\Repository\PositionRepository;
use App\Domain\Shift\Repository\WorkShiftRepository;
use App\Service\Audit\AuditLogger;
use App\Service\Auth\AuthSession;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class EmployeeController extends AbstractController
{
    private const ENTITY = PermissionRepository::ENTITY_EMPLOYEES;
    private const HISTORY_ENTITY = PermissionRepository::ENTITY_EMPLOYEE_HISTORY;
    private const PERIOD_ENTITY = PermissionRepository::ENTITY_EMPLOYEE_PERIODS;

    #[Route('/admin/employees', name: 'admin_employees_index', methods: ['GET'])]
    public function index(
        AuthSession $auth,
        EmployeeRepository $employees,
        FactoryDepartmentRepository $factoryDepartments,
        FactoryBranchRepository $branches,
        DepartmentRepository $sections,
        PositionRepository $functions,
        WorkShiftRepository $shifts,
        EmployeeStatusRepository $statuses,
    ): Response {
        $this->denyPermission($auth, RolePermission::ACTION_READ);

        return $this->renderIndex($auth, $employees, $factoryDepartments, $branches, $sections, $functions, $shifts, $statuses);
    }

    #[Route('/admin/employees/create', name: 'admin_employees_create', methods: ['POST'])]
    public function create(
        Request $request,
        AuthSession $auth,
        EmployeeRepository $employees,
        FactoryDepartmentRepository $factoryDepartments,
        FactoryBranchRepository $branches,
        DepartmentRepository $sections,
        PositionRepository $functions,
        WorkShiftRepository $shifts,
        EmployeeStatusRepository $statuses,
        AuditLogger $audit,
    ): Response {
        $this->denyPermission($auth, RolePermission::ACTION_CREATE);
        $form = $this->employeeForm($request);
        $error = $this->validateEmployeeForm($form, null);

        if ($error !== null) {
            return $this->renderIndex($auth, $employees, $factoryDepartments, $branches, $sections, $functions, $shifts, $statuses, [
                'create_error' => $error,
                'open_create_modal' => true,
                'create_form' => $form,
            ]);
        }

        $employee = $employees->create($this->employeeAttributes($form), $form['assignment_date']);
        $audit->log($request, $auth, RolePermission::ACTION_CREATE, self::ENTITY, $employee->id, $employee->label(), null, $this->snapshot($employee));
        $this->addFlash('success', 'success.created');

        return $this->redirectToRoute('admin_employees_index');
    }

    #[Route('/admin/employees/{id}/update', name: 'admin_employees_update', methods: ['POST'])]
    public function update(
        int $id,
        Request $request,
        AuthSession $auth,
        EmployeeRepository $employees,
        FactoryDepartmentRepository $factoryDepartments,
        FactoryBranchRepository $branches,
        DepartmentRepository $sections,
        PositionRepository $functions,
        WorkShiftRepository $shifts,
        EmployeeStatusRepository $statuses,
        AuditLogger $audit,
    ): Response {
        $this->denyPermission($auth, RolePermission::ACTION_UPDATE);
        $employee = $employees->findById($id);
        if ($employee === null) {
            throw $this->createNotFoundException('Employee not found.');
        }

        $form = $this->employeeForm($request);
        $error = $this->validateEmployeeForm($form, $employee);
        if ($error !== null) {
            return $this->renderIndex($auth, $employees, $factoryDepartments, $branches, $sections, $functions, $shifts, $statuses, [
                'edit_error' => $error,
                'open_edit_employee_id' => $id,
                'edit_form' => $form,
            ]);
        }

        $before = $this->snapshot($employee);
        $employees->update($employee, $this->employeeAttributes($form), $form['assignment_date']);
        $audit->log($request, $auth, RolePermission::ACTION_UPDATE, self::ENTITY, $employee->id, $employee->label(), $before, $this->snapshot($employee));
        $this->addFlash('success', 'success.updated');

        return $this->redirectToRoute('admin_employees_index');
    }

    #[Route('/admin/employees/{id}/history', name: 'admin_employees_history', methods: ['GET'])]
    public function history(int $id, AuthSession $auth, EmployeeRepository $employees): Response
    {
        $this->denyHistoryPermission($auth, RolePermission::ACTION_READ);
        $employee = $employees->findById($id);
        if ($employee === null) {
            throw $this->createNotFoundException('Employee not found.');
        }

        return $this->render('admin/employees/history.html.twig', [
            'current_user' => $auth->user(),
            'employee' => $employee->load(['department', 'section', 'position', 'shift']),
            'assignment_history' => $employees->assignmentHistory($employee),
            'periods' => $employees->periods($employee),
            'can_create_period' => $auth->can(self::PERIOD_ENTITY, RolePermission::ACTION_CREATE),
            'can_update_period' => $auth->can(self::PERIOD_ENTITY, RolePermission::ACTION_UPDATE),
            'can_delete_period' => $auth->can(self::PERIOD_ENTITY, RolePermission::ACTION_DELETE),
            'edit_period_id' => null,
            'period_form' => ['period_type' => EmployeePeriod::TYPE_VACATION, 'date_from' => '', 'date_to' => ''],
            'period_error' => null,
        ]);
    }

    #[Route('/admin/employees/{id}/history/period', name: 'admin_employees_add_period', methods: ['POST'])]
    public function addPeriod(int $id, Request $request, AuthSession $auth, EmployeeRepository $employees, AuditLogger $audit): Response
    {
        $this->denyPeriodPermission($auth, RolePermission::ACTION_CREATE);
        $employee = $employees->findById($id);
        if ($employee === null) {
            throw $this->createNotFoundException('Employee not found.');
        }

        $type = (string) $request->request->get('period_type', '');
        $dateFrom = trim((string) $request->request->get('date_from', ''));
        $dateTo = trim((string) $request->request->get('date_to', '')) ?: null;
        $error = $this->validatePeriod($type, $dateFrom, $dateTo);
        if ($error === null && $type === EmployeePeriod::TYPE_VACATION && $this->hasOverlappingVacation($employee, $dateFrom, $dateTo)) {
            $error = 'error.employee_vacation_overlap';
        }
        if ($error !== null) {
            return $this->render('admin/employees/history.html.twig', [
                'current_user' => $auth->user(),
                'employee' => $employee->load(['department', 'section', 'position', 'shift']),
                'assignment_history' => $employees->assignmentHistory($employee),
                'periods' => $employees->periods($employee),
                'can_create_period' => true,
                'can_update_period' => $auth->can(self::PERIOD_ENTITY, RolePermission::ACTION_UPDATE),
                'can_delete_period' => $auth->can(self::PERIOD_ENTITY, RolePermission::ACTION_DELETE),
                'edit_period_id' => null,
                'can_update_period' => $auth->can(self::HISTORY_ENTITY, RolePermission::ACTION_UPDATE),
                'can_delete_period' => $auth->can(self::HISTORY_ENTITY, RolePermission::ACTION_DELETE),
                'edit_period_id' => null,
                'period_form' => ['period_type' => $type, 'date_from' => $dateFrom, 'date_to' => $dateTo],
                'period_error' => $error,
            ]);
        }

        $period = $employees->addPeriod($employee, $type, $dateFrom, $dateTo);
        $audit->log($request, $auth, RolePermission::ACTION_CREATE, self::PERIOD_ENTITY, $employee->id, $employee->label(), null, [
            'period_id' => $period->id,
            'period_type' => $type,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ], ['employee_period' => true]);
        $this->addFlash('success', 'success.updated');

        return $this->redirectToRoute('admin_employees_history', ['id' => $id]);
    }

    #[Route('/admin/employees/{id}/history/period/{periodId}/update', name: 'admin_employees_update_period', methods: ['POST'])]
    public function updatePeriod(int $id, int $periodId, Request $request, AuthSession $auth, EmployeeRepository $employees, AuditLogger $audit): Response
    {
        $this->denyPeriodPermission($auth, RolePermission::ACTION_UPDATE);
        $employee = $employees->findById($id);
        $period = $employee !== null ? $employees->findPeriod($employee, $periodId) : null;
        if ($employee === null || $period === null) {
            throw $this->createNotFoundException('Employee period not found.');
        }

        $dateFrom = trim((string) $request->request->get('date_from', ''));
        $dateTo = trim((string) $request->request->get('date_to', '')) ?: null;
        $error = $this->validatePeriod($period->period_type, $dateFrom, $dateTo);
        if ($error === null && $period->period_type === EmployeePeriod::TYPE_VACATION && $this->hasOverlappingVacation($employee, $dateFrom, $dateTo, $period->id)) {
            $error = 'error.employee_vacation_overlap';
        }
        if ($error !== null) {
            return $this->renderHistoryWithPeriodForm($auth, $employees, $employee, $dateFrom, $dateTo, $error, $periodId);
        }

        $before = $this->periodSnapshot($period);
        $employees->updatePeriod($employee, $period, $dateFrom, $dateTo);
        $audit->log($request, $auth, RolePermission::ACTION_UPDATE, self::PERIOD_ENTITY, $period->id, $employee->label(), $before, $this->periodSnapshot($period), ['employee_period' => true]);
        $this->addFlash('success', 'success.updated');

        return $this->redirectToRoute('admin_employees_history', ['id' => $id]);
    }

    #[Route('/admin/employees/{id}/history/period/{periodId}/delete', name: 'admin_employees_delete_period', methods: ['POST'])]
    public function deletePeriod(int $id, int $periodId, Request $request, AuthSession $auth, EmployeeRepository $employees, AuditLogger $audit): Response
    {
        $this->denyPeriodPermission($auth, RolePermission::ACTION_DELETE);
        $employee = $employees->findById($id);
        $period = $employee !== null ? $employees->findPeriod($employee, $periodId) : null;
        if ($employee === null || $period === null) {
            return $this->redirectToRoute('admin_employees_history', ['id' => $id]);
        }

        $before = $this->periodSnapshot($period);
        $employees->deletePeriod($employee, $period);
        $audit->log($request, $auth, RolePermission::ACTION_DELETE, self::PERIOD_ENTITY, $periodId, $employee->label(), $before, null, ['employee_period' => true, 'physical_delete' => true]);
        $this->addFlash('success', 'success.deleted');

        return $this->redirectToRoute('admin_employees_history', ['id' => $id]);
    }

    private function renderHistoryWithPeriodForm(AuthSession $auth, EmployeeRepository $employees, Employee $employee, string $dateFrom, ?string $dateTo, string $error, ?int $editPeriodId = null): Response
    {
        return $this->render('admin/employees/history.html.twig', [
            'current_user' => $auth->user(),
            'employee' => $employee->load(['department', 'section', 'position', 'shift']),
            'assignment_history' => $employees->assignmentHistory($employee),
            'periods' => $employees->periods($employee),
            'can_create_period' => $auth->can(self::PERIOD_ENTITY, RolePermission::ACTION_CREATE),
            'can_update_period' => $auth->can(self::PERIOD_ENTITY, RolePermission::ACTION_UPDATE),
            'can_delete_period' => $auth->can(self::PERIOD_ENTITY, RolePermission::ACTION_DELETE),
            'edit_period_id' => $editPeriodId,
            'period_form' => ['period_type' => $employee->periods()->find($editPeriodId)?->period_type ?? EmployeePeriod::TYPE_VACATION, 'date_from' => $dateFrom, 'date_to' => $dateTo],
            'period_error' => $error,
        ]);
    }

    private function periodSnapshot(EmployeePeriod $period): array
    {
        return [
            'id' => $period->id,
            'employee_id' => $period->employee_id,
            'period_type' => $period->period_type,
            'date_from' => $period->date_from?->format('Y-m-d'),
            'date_to' => $period->date_to?->format('Y-m-d'),
        ];
    }

    private function renderIndex(
        AuthSession $auth,
        EmployeeRepository $employees,
        FactoryDepartmentRepository $factoryDepartments,
        FactoryBranchRepository $branches,
        DepartmentRepository $sections,
        PositionRepository $functions,
        WorkShiftRepository $shifts,
        EmployeeStatusRepository $statuses,
        array $overrides = [],
    ): Response {
        return $this->render('admin/employees/index.html.twig', array_replace([
            'current_user' => $auth->user(),
            'employees' => $employees->list(),
            'factory_departments' => $factoryDepartments->listActive(),
            'branches' => $branches->listActive(),
            'sections' => $sections->listActive(),
            'functions' => $functions->listActive(),
            'shifts' => $shifts->listActive(),
            'employee_statuses' => $statuses->listActive(),
            'can_create' => $auth->can(self::ENTITY, RolePermission::ACTION_CREATE),
            'can_update' => $auth->can(self::ENTITY, RolePermission::ACTION_UPDATE),
            'can_history_read' => $auth->can(self::HISTORY_ENTITY, RolePermission::ACTION_READ),
            'create_error' => null,
            'open_create_modal' => false,
            'create_form' => $this->emptyForm(),
            'edit_error' => null,
            'open_edit_employee_id' => null,
            'edit_form' => [],
        ], $overrides));
    }

    private function employeeForm(Request $request): array
    {
        return [
            'face_id' => trim((string) $request->request->get('face_id', '')),
            'name' => trim((string) $request->request->get('name', '')),
            'factory_branch_id' => (int) $request->request->get('factory_branch_id', 0),
            'factory_department_id' => (int) $request->request->get('factory_department_id', 0),
            'factory_section_id' => (int) $request->request->get('factory_section_id', 0),
            'factory_function_id' => (int) $request->request->get('factory_function_id', 0),
            'work_shift_id' => (int) $request->request->get('work_shift_id', 0) ?: null,
            'employee_status_id' => (int) $request->request->get('employee_status_id', 0),
            'last_hired_at' => trim((string) $request->request->get('last_hired_at', '')),
            'dismissed_at' => trim((string) $request->request->get('dismissed_at', '')),
            'assignment_date' => trim((string) $request->request->get('assignment_date', '')) ?: date('Y-m-d'),
            'formator' => $request->request->getBoolean('formator'),
        ];
    }

    private function emptyForm(): array
    {
        return [
            'face_id' => '', 'name' => '', 'factory_branch_id' => 0, 'factory_department_id' => 0, 'factory_section_id' => 0,
            'factory_function_id' => 0, 'work_shift_id' => null,
            'employee_status_id' => EmployeeStatus::query()->where('code', 'active')->value('id') ?? 0,
            'last_hired_at' => '',
            'dismissed_at' => '', 'assignment_date' => date('Y-m-d'), 'formator' => false,
        ];
    }

    private function validateEmployeeForm(array $form, ?Employee $employee): ?string
    {
        if ($form['face_id'] === '' || $form['name'] === '') {
            return 'error.required_employee_fields';
        }
        if (!FactoryBranch::query()->whereKey($form['factory_branch_id'])->where('active', true)->exists()) {
            return 'error.invalid_employee_structure';
        }
        $status = EmployeeStatus::query()->whereKey($form['employee_status_id'])->where('active', true)->first();
        if ($status === null) {
            return 'error.invalid_employee_status';
        }
        if ($employee !== null && !$status->isVacation() && $this->hasActiveVacation($employee)) {
            return 'error.employee_vacation_status_locked';
        }
        if ($status->code === 'inactive' && $form['dismissed_at'] === '') {
            return 'error.required_employee_dismissal_date';
        }
        if (!$this->validDate($form['last_hired_at']) || !$this->validDate($form['dismissed_at']) || !$this->validDate($form['assignment_date'])) {
            return 'error.invalid_employee_date';
        }
        if ($form['last_hired_at'] !== '' && $form['dismissed_at'] !== '' && $form['dismissed_at'] < $form['last_hired_at']) {
            return 'error.invalid_employee_date_range';
        }
        if (Employee::query()->where('face_id', $form['face_id'])->when($employee !== null, static fn ($query) => $query->where('id', '!=', $employee->id))->exists()) {
            return 'error.duplicate_employee_face_id';
        }
        if (!Department::query()->where('id', $form['factory_section_id'])->where('factory_department_id', $form['factory_department_id'])->exists()) {
            return 'error.invalid_employee_structure';
        }
        if (!Position::query()->where('id', $form['factory_function_id'])->whereHas('departments', static fn ($query) => $query->where('factory_sections.id', $form['factory_section_id']))->exists()) {
            return 'error.invalid_employee_structure';
        }

        return null;
    }

    private function hasActiveVacation(Employee $employee): bool
    {
        $today = date('Y-m-d');

        return EmployeePeriod::query()
            ->where('employee_id', $employee->id)
            ->where('period_type', EmployeePeriod::TYPE_VACATION)
            ->where('date_from', '<=', $today)
            ->where(static function ($query) use ($today): void {
                $query->whereNull('date_to')->orWhere('date_to', '>=', $today);
            })
            ->exists();
    }

    private function hasOverlappingVacation(Employee $employee, string $dateFrom, ?string $dateTo, ?int $exceptPeriodId = null): bool
    {
        $query = EmployeePeriod::query()
            ->where('employee_id', $employee->id)
            ->where('period_type', EmployeePeriod::TYPE_VACATION)
            ->where('date_from', '<=', $dateTo ?? '9999-12-31')
            ->where(static function ($query) use ($dateFrom): void {
                $query->whereNull('date_to')->orWhere('date_to', '>=', $dateFrom);
            });

        if ($exceptPeriodId !== null) {
            $query->where('id', '!=', $exceptPeriodId);
        }

        return $query->exists();
    }

    private function validatePeriod(string $type, string $dateFrom, ?string $dateTo): ?string
    {
        if (!in_array($type, [EmployeePeriod::TYPE_VACATION, EmployeePeriod::TYPE_DISMISSAL], true) || !$this->validDate($dateFrom) || ($dateTo !== null && !$this->validDate($dateTo))) {
            return 'error.invalid_employee_period';
        }
        if ($dateTo !== null && $dateTo < $dateFrom) {
            return 'error.invalid_employee_period_range';
        }

        return null;
    }

    private function validDate(string $value): bool
    {
        return $value === '' || DateTimeImmutable::createFromFormat('!Y-m-d', $value)?->format('Y-m-d') === $value;
    }

    private function employeeAttributes(array $form): array
    {
        return [
            'face_id' => $form['face_id'], 'name' => $form['name'],
            'factory_branch_id' => $form['factory_branch_id'],
            'factory_department_id' => $form['factory_department_id'], 'factory_section_id' => $form['factory_section_id'],
            'factory_function_id' => $form['factory_function_id'], 'work_shift_id' => $form['work_shift_id'],
            'employee_status_id' => $form['employee_status_id'] ?: null,
            'status' => EmployeeStatus::query()->whereKey($form['employee_status_id'])->value('code') ?? 'inactive',
            'last_hired_at' => $form['last_hired_at'] ?: null,
            'dismissed_at' => $form['dismissed_at'] ?: null, 'formator' => $form['formator'],
        ];
    }

    private function snapshot(Employee $employee): array
    {
        $employee->load(['branch', 'department', 'section', 'position', 'shift', 'employeeStatus']);

        return [
            'id' => $employee->id, 'face_id' => $employee->face_id, 'name' => $employee->name,
            'branch' => $employee->branch?->label(),
            'department' => $employee->department?->label(), 'section' => $employee->section?->label(),
            'function' => $employee->position?->label(), 'shift' => $employee->shift?->label(),
            'status' => $employee->employeeStatus?->label(), 'status_code' => $employee->employeeStatus?->code,
            'employee_status_id' => $employee->employee_status_id,
            'employee_status' => $employee->employeeStatus?->label(), 'last_hired_at' => $employee->last_hired_at?->format('Y-m-d'),
            'dismissed_at' => $employee->dismissed_at?->format('Y-m-d'), 'formator' => (bool) $employee->formator,
        ];
    }

    private function denyPermission(AuthSession $auth, string $action): void
    {
        if (!$auth->can(self::ENTITY, $action)) {
            throw new AccessDeniedHttpException('Permission denied.');
        }
    }

    private function denyHistoryPermission(AuthSession $auth, string $action): void
    {
        if (!$auth->can(self::HISTORY_ENTITY, $action)) {
            throw new AccessDeniedHttpException('Permission denied.');
        }
    }

    private function denyPeriodPermission(AuthSession $auth, string $action): void
    {
        if (!$auth->can(self::PERIOD_ENTITY, $action)) {
            throw new AccessDeniedHttpException('Permission denied.');
        }
    }
}
