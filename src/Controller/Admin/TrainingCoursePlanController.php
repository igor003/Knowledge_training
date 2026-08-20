<?php

namespace App\Controller\Admin;

use App\Domain\Access\Model\RolePermission;
use App\Domain\Access\Repository\PermissionRepository;
use App\Domain\FactoryDepartment\Model\FactoryDepartment;
use App\Domain\TrainingCoursePlan\Repository\TrainingCoursePlanRepository;
use App\Service\Audit\AuditLogger;
use App\Service\Auth\AuthSession;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class TrainingCoursePlanController extends AbstractController
{
    #[Route('/admin/training-course-plans', name: 'admin_training_course_plans_index', methods: ['GET'])]
    public function index(Request $request, AuthSession $auth, TrainingCoursePlanRepository $plans): Response
    {
        $this->denyPermission($auth, PermissionRepository::ENTITY_TRAINING_COURSE_PLANS, RolePermission::ACTION_READ);

        $year = $this->yearFromRequest($request);
        $departments = $plans->listActiveDepartmentsWithCourses();
        $selectedDepartmentId = (int) $request->query->get('department_id', 0);
        $selectedDepartment = $selectedDepartmentId > 0
            ? $plans->findDepartmentWithCourses($selectedDepartmentId)
            : null;

        return $this->renderIndex($auth, $plans, $departments, $selectedDepartment, $year);
    }

    #[Route('/admin/training-course-plans/save', name: 'admin_training_course_plans_save', methods: ['POST'])]
    public function save(Request $request, AuthSession $auth, TrainingCoursePlanRepository $plans, AuditLogger $audit): Response
    {
        $this->denyPermission($auth, PermissionRepository::ENTITY_TRAINING_COURSE_PLANS, RolePermission::ACTION_UPDATE);

        $year = $this->yearFromRequest($request);
        $departmentId = (int) $request->request->get('department_id', 0);
        $departments = $departmentId > 0
            ? array_filter([$plans->findDepartmentWithCourses($departmentId)])
            : $plans->listActiveDepartmentsWithCourses()->all();

        if ($departments === []) {
            $this->addFlash('error', 'error.training_plan_department_required');

            return $this->redirectToRoute('admin_training_course_plans_index', ['year' => $year]);
        }

        $scopeLabel = $departmentId > 0 && $departments[0] instanceof FactoryDepartment
            ? $departments[0]->label()
            : 'all';
        $before = $plans->snapshotForDepartments($departments, $year, $scopeLabel);
        $values = $this->planValuesFromRequest((array) $request->request->all('plans'));
        $plans->saveDepartmentsYear($departments, $year, $values);
        $after = $plans->snapshotForDepartments($departments, $year, $scopeLabel);

        $audit->log(
            $request,
            $auth,
            RolePermission::ACTION_UPDATE,
            PermissionRepository::ENTITY_TRAINING_COURSE_PLANS,
            $departmentId > 0 ? (int) $departments[0]->id : null,
            $scopeLabel . ' / ' . $year,
            $before,
            $after,
            ['year' => $year, 'department_id' => $departmentId > 0 ? (int) $departments[0]->id : null],
        );
        $this->addFlash('success', 'success.updated');

        $redirectDepartmentId = max(0, (int) $request->request->get('redirect_department_id', $departmentId));
        $routeParameters = ['year' => $year];
        if ($redirectDepartmentId > 0) {
            $routeParameters['department_id'] = $redirectDepartmentId;
        }

        return $this->redirectToRoute('admin_training_course_plans_index', $routeParameters);
    }

    /**
     * @param iterable<int, FactoryDepartment> $departments
     */
    private function renderIndex(
        AuthSession $auth,
        TrainingCoursePlanRepository $plans,
        iterable $departments,
        ?FactoryDepartment $selectedDepartment,
        int $year,
    ): Response {
        return $this->render('admin/training_course_plans/index.html.twig', [
            'current_user' => $auth->user(),
            'departments' => $departments,
            'selected_department' => $selectedDepartment,
            'year' => $year,
            'years' => range((int) date('Y') - 2, (int) date('Y') + 5),
            'months' => range(1, 12),
            'rows' => $selectedDepartment instanceof FactoryDepartment
                ? $plans->matrixRowsForDepartment($selectedDepartment, $year)
                : $plans->matrixRowsForDepartments($departments, $year),
            'can_update' => $auth->can(PermissionRepository::ENTITY_TRAINING_COURSE_PLANS, RolePermission::ACTION_UPDATE),
        ]);
    }

    private function yearFromRequest(Request $request): int
    {
        $year = (int) ($request->request->get('year') ?: $request->query->get('year') ?: date('Y'));

        if ($year < 2000 || $year > 2100) {
            return (int) date('Y');
        }

        return $year;
    }

    /**
     * @param array<int|string, mixed> $rawPlans
     * @return array<int, array<int, array<int, array{planned: int}>>>
     */
    private function planValuesFromRequest(array $rawPlans): array
    {
        $values = [];

        foreach ($rawPlans as $departmentId => $courses) {
            if (!is_array($courses)) {
                continue;
            }

            $departmentId = (int) $departmentId;
            if ($departmentId <= 0) {
                continue;
            }

            foreach ($courses as $courseId => $months) {
                if (!is_array($months)) {
                    continue;
                }

                $courseId = (int) $courseId;
                if ($courseId <= 0) {
                    continue;
                }

                for ($month = 1; $month <= 12; $month++) {
                    $monthValues = $months[$month] ?? [];
                    if (!is_array($monthValues)) {
                        $monthValues = [];
                    }

                    $values[$departmentId][$courseId][$month] = [
                        'planned' => max(0, (int) ($monthValues['planned'] ?? 0)),
                    ];
                }
            }
        }

        return $values;
    }

    private function denyPermission(AuthSession $auth, string $entityCode, string $action): void
    {
        if (!$auth->can($entityCode, $action)) {
            throw new AccessDeniedHttpException('Permission denied.');
        }
    }
}
