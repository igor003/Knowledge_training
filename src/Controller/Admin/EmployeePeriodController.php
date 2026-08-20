<?php

namespace App\Controller\Admin;

use App\Domain\Access\Model\RolePermission;
use App\Domain\Access\Repository\PermissionRepository;
use App\Domain\Employee\Model\Employee;
use App\Domain\Employee\Model\EmployeePeriod;
use App\Domain\Employee\Repository\EmployeePeriodRepository;
use App\Domain\Employee\Repository\EmployeeRepository;
use App\Service\Audit\AuditLogger;
use App\Service\Auth\AuthSession;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class EmployeePeriodController extends AbstractController
{
    private const ENTITY = PermissionRepository::ENTITY_EMPLOYEE_PERIODS;

    #[Route('/admin/employee-periods', name: 'admin_employee_periods_index', methods: ['GET'])]
    public function index(AuthSession $auth, EmployeePeriodRepository $periods, EmployeeRepository $employees): Response
    {
        $this->deny($auth, RolePermission::ACTION_READ);

        return $this->renderIndex($auth, $periods, $employees);
    }

    #[Route('/admin/employee-periods/create', name: 'admin_employee_periods_create', methods: ['POST'])]
    public function create(Request $request, AuthSession $auth, EmployeePeriodRepository $periods, EmployeeRepository $employees, AuditLogger $audit): Response
    {
        $this->deny($auth, RolePermission::ACTION_CREATE);
        $form = $this->form($request);
        $employee = $form['employee_id'] > 0 ? $employees->findById($form['employee_id']) : null;
        $error = $this->validate($form, $employee, $periods);

        if ($error !== null) {
            return $this->renderIndex($auth, $periods, $employees, ['create_error' => $error, 'open_create_modal' => true, 'create_form' => $form]);
        }

        $period = $periods->create([
            'employee_id' => $employee->id,
            'period_type' => $form['period_type'],
            'date_from' => $form['date_from'],
            'date_to' => $form['date_to'],
            'note' => $form['note'],
        ]);
        $employees->refreshAutomaticStatus($employee);
        $audit->log($request, $auth, RolePermission::ACTION_CREATE, self::ENTITY, $period->id, $employee->label(), null, $this->snapshot($period), ['employee_period' => true]);
        $this->addFlash('success', 'success.created');

        return $this->redirectToRoute('admin_employee_periods_index');
    }

    #[Route('/admin/employee-periods/{id}/update', name: 'admin_employee_periods_update', methods: ['POST'])]
    public function update(int $id, Request $request, AuthSession $auth, EmployeePeriodRepository $periods, EmployeeRepository $employees, AuditLogger $audit): Response
    {
        $this->deny($auth, RolePermission::ACTION_UPDATE);
        $period = $periods->findById($id);
        if ($period === null || $period->employee === null) {
            throw $this->createNotFoundException('Employee period not found.');
        }

        $form = $this->form($request, $period);
        $error = $this->validate($form, $period->employee, $periods, $period->id);
        if ($error !== null) {
            return $this->renderIndex($auth, $periods, $employees, ['edit_error' => $error, 'open_edit_period_id' => $id, 'edit_form' => $form]);
        }

        $before = $this->snapshot($period);
        $periods->update($period, $form['date_from'], $form['date_to'], $form['note']);
        $employees->refreshAutomaticStatus($period->employee);
        $audit->log($request, $auth, RolePermission::ACTION_UPDATE, self::ENTITY, $period->id, $period->employee->label(), $before, $this->snapshot($period), ['employee_period' => true]);
        $this->addFlash('success', 'success.updated');

        return $this->redirectToRoute('admin_employee_periods_index');
    }

    #[Route('/admin/employee-periods/{id}/delete', name: 'admin_employee_periods_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, AuthSession $auth, EmployeePeriodRepository $periods, EmployeeRepository $employees, AuditLogger $audit): Response
    {
        $this->deny($auth, RolePermission::ACTION_DELETE);
        $period = $periods->findById($id);
        if ($period === null || $period->employee === null) {
            return $this->redirectToRoute('admin_employee_periods_index');
        }

        $employee = $period->employee;
        $before = $this->snapshot($period);
        $periods->delete($period);
        $employees->refreshAutomaticStatus($employee);
        $audit->log($request, $auth, RolePermission::ACTION_DELETE, self::ENTITY, $id, $employee->label(), $before, null, ['employee_period' => true, 'physical_delete' => true]);
        $this->addFlash('success', 'success.deleted');

        return $this->redirectToRoute('admin_employee_periods_index');
    }

    #[Route('/admin/employee-periods/{id}/deactivate', name: 'admin_employee_periods_deactivate', methods: ['POST'])]
    public function deactivate(int $id, Request $request, AuthSession $auth, EmployeePeriodRepository $periods, EmployeeRepository $employees, AuditLogger $audit): Response
    {
        $this->deny($auth, RolePermission::ACTION_DEACTIVATE);
        $period = $periods->findById($id);
        if ($period === null || $period->employee === null || !$period->active) {
            return $this->redirectToRoute('admin_employee_periods_index');
        }

        $employee = $period->employee;
        $before = $this->snapshot($period);
        $periods->deactivate($period);
        $employees->refreshAutomaticStatus($employee);
        $audit->log($request, $auth, RolePermission::ACTION_DEACTIVATE, self::ENTITY, $id, $employee->label(), $before, $this->snapshot($period), ['employee_period' => true]);
        $this->addFlash('success', 'success.deactivated');

        return $this->redirectToRoute('admin_employee_periods_index');
    }

    private function renderIndex(AuthSession $auth, EmployeePeriodRepository $periods, EmployeeRepository $employees, array $overrides = []): Response
    {
        return $this->render('admin/employee_periods/index.html.twig', array_replace([
            'current_user' => $auth->user(),
            'periods' => $periods->list(),
            'employees' => $employees->list(),
            'can_create' => $auth->can(self::ENTITY, RolePermission::ACTION_CREATE),
            'can_update' => $auth->can(self::ENTITY, RolePermission::ACTION_UPDATE),
            'can_delete' => $auth->can(self::ENTITY, RolePermission::ACTION_DELETE),
            'can_deactivate' => $auth->can(self::ENTITY, RolePermission::ACTION_DEACTIVATE),
            'create_error' => null,
            'open_create_modal' => false,
            'create_form' => ['employee_id' => 0, 'period_type' => EmployeePeriod::TYPE_VACATION, 'date_from' => '', 'date_to' => '', 'note' => ''],
            'edit_error' => null,
            'open_edit_period_id' => null,
            'edit_form' => [],
        ], $overrides));
    }

    private function form(Request $request, ?EmployeePeriod $period = null): array
    {
        return [
            'employee_id' => (int) $request->request->get('employee_id', $period?->employee_id ?? 0),
            'period_type' => (string) $request->request->get('period_type', $period?->period_type ?? EmployeePeriod::TYPE_VACATION),
            'date_from' => trim((string) $request->request->get('date_from', $period?->date_from?->format('Y-m-d') ?? '')),
            'date_to' => trim((string) $request->request->get('date_to', $period?->date_to?->format('Y-m-d') ?? '')) ?: null,
            'note' => trim((string) $request->request->get('note', $period?->note ?? '')) ?: null,
        ];
    }

    private function validate(array $form, ?Employee $employee, EmployeePeriodRepository $periods, ?int $exceptId = null): ?string
    {
        if ($employee === null) {
            return 'error.invalid_employee_period_employee';
        }
        if (!in_array($form['period_type'], [EmployeePeriod::TYPE_VACATION, EmployeePeriod::TYPE_DISMISSAL], true)) {
            return 'error.invalid_employee_period';
        }
        if (!$this->validDate($form['date_from']) || ($form['date_to'] !== null && !$this->validDate($form['date_to']))) {
            return 'error.invalid_employee_date';
        }
        if ($form['date_to'] !== null && $form['date_to'] < $form['date_from']) {
            return 'error.invalid_employee_period_range';
        }
        if ($form['period_type'] === EmployeePeriod::TYPE_VACATION && $periods->hasOverlappingVacation($employee->id, $form['date_from'], $form['date_to'], $exceptId)) {
            return 'error.employee_vacation_overlap';
        }

        return null;
    }

    private function validDate(string $value): bool
    {
        return $value !== '' && DateTimeImmutable::createFromFormat('!Y-m-d', $value)?->format('Y-m-d') === $value;
    }

    private function snapshot(EmployeePeriod $period): array
    {
        return [
            'id' => $period->id,
            'employee_id' => $period->employee_id,
            'period_type' => $period->period_type,
            'date_from' => $period->date_from?->format('Y-m-d'),
            'date_to' => $period->date_to?->format('Y-m-d'),
            'note' => $period->note,
            'active' => (bool) $period->active,
        ];
    }

    private function deny(AuthSession $auth, string $action): void
    {
        if (!$auth->can(self::ENTITY, $action)) {
            throw new AccessDeniedHttpException('Permission denied.');
        }
    }
}
